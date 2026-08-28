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
     * Create one active subscription from a live Outline key record
     * (`id`, `name`, `accessUrl`). Shared by Import (on Add Server), Sync
     * now's import action, and the reconciliation cron — none of those
     * re-implement subscription creation from a raw key. Callers compute
     * the expiry date; this method does no date math.
     *
     * @param array<string, mixed> $outlineKey
     * @return array<string, mixed>
     */
    public function createFromOutlineKey(string $serverId, array $outlineKey, \DateTimeImmutable $expiryDate): array
    {
        $name = (string) ($outlineKey['name'] ?? '');

        $subscription = $this->cockpit->createItem('subscriptions', [
            'recipientName' => $name,
            'keyName'       => $name,
            'notes'         => '',
            'serverId'      => $serverId,
            'outlineKeyId'  => (string) ($outlineKey['id'] ?? ''),
            'accessUrl'     => (string) ($outlineKey['accessUrl'] ?? ''),
            'status'        => 'active',
            'expiryDate'    => $expiryDate->format('Y-m-d'),
            'token'         => self::generateToken(),
        ]);

        if ($subscription === null) {
            throw new \RuntimeException('Failed to save the subscription to Cockpit.');
        }

        return $subscription;
    }

    /**
     * Import every live Outline key on a server as an active subscription,
     * continuing past individual Cockpit write failures.
     *
     * @return array{imported: int, failed: int, failures: array<int, array{name: string, error: string}>}
     */
    public function importAllFromServer(string $serverId, string $apiUrl, \DateTimeImmutable $expiryDate): array
    {
        $imported  = 0;
        $failed    = 0;
        $failures  = [];

        foreach ($this->outline->listKeys($apiUrl) as $key) {
            try {
                $this->createFromOutlineKey($serverId, $key, $expiryDate);
                $imported++;
            } catch (\Throwable $e) {
                $failed++;
                $failures[] = ['name' => (string) ($key['name'] ?? ''), 'error' => $e->getMessage()];
            }
        }

        return ['imported' => $imported, 'failed' => $failed, 'failures' => $failures];
    }

    /**
     * Resolve the "found on server" section of a sync diff into
     * subscriptions. `$pastedText` carries optional `key_name: date` lines;
     * a key with a matched, valid, today-or-future date uses it, everything
     * else uses the 1-month default term. Continues past individual
     * failures.
     *
     * @param array<int, array<string, mixed>> $keys  the diff's foundOnServer list
     * @return array<int, array{name: string, status: string, expiryDate?: string, error?: string}>
     */
    public function resolveFoundOnServer(string $serverId, array $keys, string $pastedText): array
    {
        $pastedDates = $this->parsePastedDates($pastedText);
        $defaultExpiry = self::addMonthsClamped($this->today(), 1);
        $results = [];

        foreach ($keys as $key) {
            $name = (string) ($key['name'] ?? '');
            $expiry = $defaultExpiry;

            if (isset($pastedDates[$name])) {
                try {
                    $parsed = (new \DateTimeImmutable($pastedDates[$name]))->setTime(0, 0);
                    if ($parsed >= $this->today()) {
                        $expiry = $parsed;
                    }
                } catch (\Exception $e) {
                    // Malformed date line — fall back to the default term.
                }
            }

            try {
                $this->createFromOutlineKey($serverId, $key, $expiry);
                $results[] = ['name' => $name, 'status' => 'resolved', 'expiryDate' => $expiry->format('Y-m-d')];
            } catch (\Throwable $e) {
                $results[] = ['name' => $name, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Delete a subscription's Cockpit record only — no Outline call. Used
     * to resolve a "missing on server" diff row, where the key is already
     * confirmed absent.
     */
    public function removeRecord(string $id): bool
    {
        return $this->cockpit->deleteItem('subscriptions', $id);
    }

    /**
     * Parse `key_name: date` lines into a name => raw-date map. Tolerates
     * surrounding whitespace; ignores blank and colon-less lines.
     *
     * @return array<string, string>
     */
    private function parsePastedDates(string $text): array
    {
        $dates = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$name, $date] = explode(':', $line, 2);
            $name = trim($name);

            if ($name !== '') {
                $dates[$name] = trim($date);
            }
        }

        return $dates;
    }

    /**
     * Active subscriptions whose expiry is more than the configured grace
     * period in the past — the records the expiry job should process.
     *
     * Eligible when: today > expiryDate + gracePeriodDays. A record exactly
     * on that boundary is not yet eligible. Skips disabled records (no live
     * key) and already-expired ones (already processed).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findExpirable(): array
    {
        $graceDays = config('Expiry')->gracePeriodDays;
        $cutoff = $this->today()->modify("-{$graceDays} days")->format('Y-m-d');

        return array_values(array_filter(
            $this->cockpit->getCollectionCached('subscriptions'),
            static fn (array $subscription): bool => ($subscription['status'] ?? null) === 'active'
                && (string) ($subscription['expiryDate'] ?? '') !== ''
                && (string) $subscription['expiryDate'] < $cutoff,
        ));
    }

    /**
     * Delete an expired subscription's Outline key and mark it expired.
     *
     * A key that is already gone on the Outline server counts as success —
     * the desired end state holds. Only a genuine transport/HTTP failure
     * leaves the record untouched so the next run retries it.
     *
     * @param array<string, mixed> $subscription
     * @return array{id: string, outcome: string, error?: string}
     */
    public function processExpiry(array $subscription): array
    {
        $id = (string) ($subscription['_id'] ?? '');

        try {
            $server = $this->findServerById((string) ($subscription['serverId'] ?? ''));
            $this->outline->deleteKey((string) $server['apiUrl'], (string) ($subscription['keyName'] ?? ''));
        } catch (OutlineRequestException $e) {
            if (! $e->isNotFound()) {
                return ['id' => $id, 'outcome' => 'failed', 'error' => $e->getMessage()];
            }
        } catch (\Throwable $e) {
            return ['id' => $id, 'outcome' => 'failed', 'error' => $e->getMessage()];
        }

        $this->cockpit->updateItem('subscriptions', $id, ['status' => 'expired']);

        return ['id' => $id, 'outcome' => 'expired'];
    }

    /**
     * Find a subscription using its public recipient token.
     *
     * @return array<string, mixed>|null
     */
    public function findByToken(string $token): ?array
    {
        $subscriptions = $this->cockpit->getCollectionCached('subscriptions', [
            'filter' => ['token' => $token],
        ], 60);

        return $subscriptions[0] ?? null;
    }

    /**
     * Resolve the recipient page state without exposing subscription data for
     * disabled, expired, or unknown links.
     *
     * @param array<string, mixed>|null $subscription
     */
    public function resolveRecipientState(?array $subscription): string
    {
        if ($subscription === null) {
            return 'not_found';
        }

        if (($subscription['status'] ?? null) === 'disabled') {
            return 'disabled';
        }

        // Both an overdue-but-not-yet-processed subscription and one the
        // expiry job has already marked `expired` must render identically.
        if (($subscription['status'] ?? null) === 'expired') {
            return 'expired';
        }

        if (($subscription['status'] ?? null) === 'active'
            && (string) ($subscription['expiryDate'] ?? '') < $this->today()->format('Y-m-d')) {
            return 'expired';
        }

        return 'active';
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
     * Move a subscription to a different active saved server.
     *
     * @return array<string, mixed>
     */
    public function move(string $id, string $destinationServerId): array
    {
        $subscription = $this->findSubscription($id);
        if ($destinationServerId === (string) $subscription['serverId']) {
            throw new \InvalidArgumentException('The destination server must differ from the current server.');
        }

        $this->findActiveServer($destinationServerId);

        return $this->replaceKey($subscription, $destinationServerId);
    }

    /**
     * Permanently remove a subscription, deleting its live key first when
     * the subscription is active.
     */
    public function delete(string $id): void
    {
        $subscription = $this->findSubscription($id);

        if (($subscription['status'] ?? null) === 'active') {
            $server = $this->findActiveServer((string) $subscription['serverId']);
            $this->outline->deleteKeyById((string) $server['apiUrl'], (string) $subscription['outlineKeyId']);
        }

        if (!$this->cockpit->deleteItem('subscriptions', $id)) {
            throw new \RuntimeException('Failed to delete the subscription from Cockpit.');
        }
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
     * Resolve a saved server by id regardless of its active state. The
     * expiry job still needs to delete keys on servers that have since been
     * deactivated.
     *
     * @return array<string, mixed>
     */
    private function findServerById(string $serverId): array
    {
        foreach ($this->savedServers->list() as $server) {
            if (($server['_id'] ?? null) === $serverId) {
                return $server;
            }
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
