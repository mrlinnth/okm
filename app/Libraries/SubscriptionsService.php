<?php

declare(strict_types=1);

namespace App\Libraries;

use Config\Services;

/**
 * Coordinates subscription lifecycle operations.
 */
class SubscriptionsService
{
    protected CockpitService $cockpit;
    protected SavedServersService $savedServers;
    protected OutlineService $outline;

    public function __construct(
        ?CockpitService $cockpit = null,
        ?SavedServersService $savedServers = null,
        ?OutlineService $outline = null,
    ) {
        $this->cockpit = $cockpit ?? Services::cockpit();
        $this->savedServers = $savedServers ?? Services::savedServers();
        $this->outline = $outline ?? Services::outline();
    }

    /**
     * Count subscriptions assigned to a saved Outline server.
     */
    public function countByServer(string $serverId): int
    {
        return count($this->cockpit->getCollectionCached('subscriptions', [
            'filter' => ['serverId' => $serverId],
        ]));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $subscriptions = $this->cockpit->getCollectionCached('subscriptions');

        usort(
            $subscriptions,
            static fn (array $left, array $right): int => (string) ($left['expiryDate'] ?? '') <=> (string) ($right['expiryDate'] ?? ''),
        );

        return $subscriptions;
    }

    /**
     * Create an active Outline key and its matching Cockpit subscription.
     *
     * @return array<string, mixed>
     */
    public function create(
        string $recipientName,
        string $keyName,
        string $serverId,
        int $durationMonths,
        ?string $notes,
    ): array {
        $server = $this->findActiveServer($serverId);
        $key = $this->outline->createKey((string) $server['apiUrl'], $keyName);
        $token = self::generateToken();
        $expiryDate = self::addMonthsClamped($this->today(), $durationMonths)->format('Y-m-d');

        $subscription = $this->cockpit->createItem('subscriptions', [
            'recipientName' => $recipientName,
            'keyName'       => $keyName,
            'notes'         => $notes ?? '',
            'serverId'      => $serverId,
            'outlineKeyId'  => $key['id'],
            'accessUrl'     => $key['accessUrl'],
            'status'        => 'active',
            'expiryDate'    => $expiryDate,
            'token'         => $token,
        ]);

        if ($subscription === null) {
            throw new \RuntimeException('Failed to save the subscription to Cockpit.');
        }

        $subscription['shareLink'] = base_url('/s/' . $token);

        return $subscription;
    }

    /**
     * @return array<string, mixed>
     */
    public function rename(string $id, ?string $recipientName, ?string $keyName): array
    {
        $subscription = $this->cockpit->getItemCached('subscriptions', $id);
        if ($subscription === null) {
            throw new \InvalidArgumentException('The subscription was not found.');
        }

        $changes = [];
        if ($recipientName !== null) {
            $changes['recipientName'] = $recipientName;
        }

        if ($keyName !== null) {
            if (($subscription['status'] ?? null) === 'active') {
                $server = $this->findActiveServer((string) $subscription['serverId']);
                $this->outline->renameKey(
                    (string) $server['apiUrl'],
                    (string) $subscription['outlineKeyId'],
                    $keyName,
                );
            }

            $changes['keyName'] = $keyName;
        }

        $updated = $this->cockpit->updateItem('subscriptions', $id, $changes);
        if ($updated === null) {
            throw new \RuntimeException('Failed to update the subscription in Cockpit.');
        }

        return $updated;
    }

    /**
     * Extend a subscription from whichever date is later: today or its
     * current expiry date.
     *
     * @return array<string, mixed>
     */
    public function extend(string $id): array
    {
        $subscription = $this->findSubscription($id);
        $today = $this->today();
        $currentExpiry = new \DateTimeImmutable((string) $subscription['expiryDate']);
        $baseDate = $currentExpiry > $today ? $currentExpiry : $today;

        $updated = $this->cockpit->updateItem('subscriptions', $id, [
            'expiryDate' => self::addMonthsClamped($baseDate, 1)->format('Y-m-d'),
        ]);
        if ($updated === null) {
            throw new \RuntimeException('Failed to update the subscription in Cockpit.');
        }

        return $updated;
    }

    /**
     * Set a subscription expiry date, rejecting dates before today.
     *
     * @return array<string, mixed>
     */
    public function setExpiry(string $id, \DateTimeImmutable $date): array
    {
        $this->findSubscription($id);

        if ($date < $this->today()) {
            throw new \InvalidArgumentException('expiryDate must be today or later.');
        }

        $updated = $this->cockpit->updateItem('subscriptions', $id, [
            'expiryDate' => $date->format('Y-m-d'),
        ]);
        if ($updated === null) {
            throw new \RuntimeException('Failed to update the subscription in Cockpit.');
        }

        return $updated;
    }

    /**
     * Delete an active Outline key and mark its subscription as disabled.
     *
     * @return array<string, mixed>
     */
    public function disable(string $id): array
    {
        $subscription = $this->findSubscription($id);
        $server = $this->findActiveServer((string) $subscription['serverId']);

        $this->outline->deleteKey((string) $server['apiUrl'], (string) $subscription['keyName']);

        $updated = $this->cockpit->updateItem('subscriptions', $id, ['status' => 'disabled']);
        if ($updated === null) {
            throw new \RuntimeException('Failed to update the subscription in Cockpit.');
        }

        return $updated;
    }

    /**
     * Create a replacement Outline key and activate its subscription.
     *
     * @return array<string, mixed>
     */
    public function enable(string $id): array
    {
        $subscription = $this->findSubscription($id);
        $server = $this->findActiveServer((string) $subscription['serverId']);
        $key = $this->outline->createKey((string) $server['apiUrl'], (string) $subscription['keyName']);

        $updated = $this->cockpit->updateItem('subscriptions', $id, [
            'outlineKeyId' => $key['id'],
            'accessUrl'    => $key['accessUrl'],
            'status'       => 'active',
        ]);
        if ($updated === null) {
            throw new \RuntimeException('Failed to update the subscription in Cockpit.');
        }

        return $updated;
    }

    /**
     * Create and persist a replacement key before best-effort cleanup of the
     * old one. This preserves a working key when source cleanup fails.
     *
     * @param array<string, mixed> $subscription
     * @return array<string, mixed>
     */
    protected function replaceKey(array $subscription, string $targetServerId): array
    {
        $targetServer = $this->findActiveServer($targetServerId);
        $oldServer = $this->findActiveServer((string) $subscription['serverId']);
        $newKey = $this->outline->createKey((string) $targetServer['apiUrl'], (string) $subscription['keyName']);

        $changes = [
            'outlineKeyId' => $newKey['id'],
            'accessUrl'    => $newKey['accessUrl'],
        ];
        if ($targetServerId !== (string) $subscription['serverId']) {
            $changes['serverId'] = $targetServerId;
        }

        $updated = $this->cockpit->updateItem('subscriptions', (string) $subscription['_id'], $changes);
        if ($updated === null) {
            throw new \RuntimeException('Failed to update the subscription in Cockpit.');
        }

        try {
            $this->outline->deleteKeyById((string) $oldServer['apiUrl'], (string) $subscription['outlineKeyId']);
        } catch (\Throwable $e) {
            $updated['warning'] = 'The old Outline key could not be deleted: ' . $e->getMessage();
        }

        return $updated;
    }

    /**
     * Issue an active subscription a new key on its current server.
     *
     * @return array<string, mixed>
     */
    public function reroll(string $id): array
    {
        $subscription = $this->findSubscription($id);
        if (($subscription['status'] ?? null) !== 'active') {
            throw new \InvalidArgumentException('Only active subscriptions can reroll their key.');
        }

        return $this->replaceKey($subscription, (string) $subscription['serverId']);
    }

    /**
     * @return array<string, mixed>
     */
    private function findActiveServer(string $serverId): array
    {
        foreach ($this->savedServers->list() as $server) {
            if (($server['_id'] ?? null) !== $serverId) {
                continue;
            }

            if (($server['active'] ?? false) !== true) {
                throw new \InvalidArgumentException('The selected server is inactive.');
            }

            return $server;
        }

        throw new \InvalidArgumentException('The selected server was not found.');
    }

    /**
     * @return array<string, mixed>
     */
    private function findSubscription(string $id): array
    {
        $subscription = $this->cockpit->getItemCached('subscriptions', $id);
        if ($subscription === null) {
            throw new \InvalidArgumentException('The subscription was not found.');
        }

        return $subscription;
    }

    protected function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today');
    }

    /**
     * Generate the immutable, URL-safe token used in recipient share links.
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Add calendar months without allowing dates such as January 31 to
     * overflow into March when the target month has fewer days.
     */
    public static function addMonthsClamped(\DateTimeImmutable $from, int $months): \DateTimeImmutable
    {
        $targetMonth = $from
            ->modify('first day of this month')
            ->modify(sprintf('%+d months', $months));

        $year = (int) $targetMonth->format('Y');
        $month = (int) $targetMonth->format('n');
        $lastDay = (int) $targetMonth->modify('last day of this month')->format('j');
        $day = min((int) $from->format('j'), $lastDay);

        return $targetMonth->setDate($year, $month, $day);
    }
}
