<?php

declare(strict_types=1);

namespace App\Libraries;

use Config\Services;

/**
 * Saved Servers registry — stores Outline server credentials in the Cockpit
 * `servers` collection so admins don't paste server JSON on every visit.
 *
 * Thin orchestration only: loose JSON validation, a light reachability check
 * delegated to OutlineService (no SSRF logic duplicated here), and Cockpit
 * reads/writes delegated to CockpitService.
 */
class SavedServersService
{
    protected CockpitService $cockpit;
    protected OutlineService $outline;

    public function __construct(?CockpitService $cockpit = null, ?OutlineService $outline = null)
    {
        $this->cockpit = $cockpit ?? Services::cockpit();
        $this->outline = $outline ?? Services::outline();
    }

    /**
     * Loose validation matching Classic key manager's Connect check exactly:
     * the JSON must parse and carry an `apiUrl` string starting with https://.
     *
     * @return array<string, mixed> The decoded server JSON
     * @throws InvalidServerJsonException
     */
    public function parseServerJson(string $json): array
    {
        $parsed = json_decode($json, true);

        if (!is_array($parsed)) {
            throw new InvalidServerJsonException('Server JSON could not be parsed.');
        }

        $apiUrl = $parsed['apiUrl'] ?? null;

        if (!is_string($apiUrl) || !str_starts_with($apiUrl, 'https://')) {
            throw new InvalidServerJsonException('Server JSON must contain an "apiUrl" starting with https://.');
        }

        return $parsed;
    }

    /**
     * Light reachability check — reuses OutlineService's existing SSRF-safe
     * request path (HTTPS-only, DNS-resolve-before-connect, blocked-range
     * checks, IP pinning). Any failure to list the server's keys means "not
     * reachable".
     */
    public function checkReachable(string $apiUrl): bool
    {
        try {
            $this->outline->listKeys($apiUrl);

            return true;
        } catch (OutlineRequestException $e) {
            return false;
        }
    }

    /**
     * Validate, light-check reachability, then create the Cockpit `servers`
     * item. Neither check failing leaves any Cockpit write behind.
     *
     * @return array<string, mixed> The created Cockpit item
     * @throws InvalidServerJsonException  invalid/malformed server JSON
     * @throws ServerUnreachableException  JSON valid but server unreachable
     * @throws \RuntimeException           Cockpit write failed
     */
    public function create(string $label, string $serverJson, ?string $publicHost): array
    {
        $parsed = $this->parseServerJson($serverJson);
        $apiUrl = (string) $parsed['apiUrl'];

        if (!$this->checkReachable($apiUrl)) {
            throw new ServerUnreachableException("Could not reach the Outline server at {$apiUrl}.");
        }

        $item = $this->cockpit->createItem('servers', [
            'label'      => $label,
            'serverJson' => $serverJson,
            'apiUrl'     => $apiUrl,
            'publicHost' => $publicHost ?? '',
            'active'     => true,
        ]);

        if ($item === null) {
            throw new \RuntimeException('Failed to save the server to Cockpit.');
        }

        return $item;
    }

    /**
     * @return array<string, mixed> The updated Cockpit item
     * @throws \RuntimeException Cockpit write failed
     */
    public function setActive(string $id, bool $active): array
    {
        $item = $this->cockpit->updateItem('servers', $id, ['active' => $active]);

        if ($item === null) {
            throw new \RuntimeException("Failed to update server {$id}.");
        }

        return $item;
    }

    public function delete(string $id): bool
    {
        return $this->cockpit->deleteItem('servers', $id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        return $this->cockpit->getCollectionCached('servers');
    }
}
