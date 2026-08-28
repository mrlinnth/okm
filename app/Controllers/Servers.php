<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\InvalidServerJsonException;
use App\Libraries\ServerUnreachableException;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Saved Servers Registry Controller
 *
 * CRUD for stored Outline server credentials (the Cockpit `servers`
 * collection). Thin — validation and delegation only; all logic lives in
 * SavedServersService. The JSON endpoints are stubbed here and implemented
 * in Phase 2.
 */
class Servers extends WebController
{
    public function index(): string
    {
        $servers = array_map(
            fn (array $server): array => $this->presentServer($server),
            Services::savedServers()->list(),
        );

        return $this->render('servers.index', [
            'title'   => 'Saved Servers',
            'servers' => $servers,
        ]);
    }

    public function store(): ResponseInterface
    {
        $body = $this->request->getJSON(true) ?? [];

        $label = $this->requireString($body, 'label');
        if ($label === null) {
            return $this->errorResponse(422, 'label is required.');
        }

        $serverJson = $this->requireString($body, 'serverJson');
        if ($serverJson === null) {
            return $this->errorResponse(422, 'serverJson is required.');
        }

        $publicHost = $this->requireString($body, 'publicHost');

        try {
            $server = Services::savedServers()->create($label, $serverJson, $publicHost);
        } catch (InvalidServerJsonException | ServerUnreachableException $e) {
            // Distinct messages preserved — the UI shows why it failed.
            return $this->errorResponse(422, $e->getMessage());
        }

        return $this->response->setJSON($this->presentServer($server));
    }

    /**
     * Trim a raw Cockpit `servers` item to the fields the UI needs. Notably
     * drops `serverJson` so the full credential payload never reaches the
     * rendered page or a JSON response.
     *
     * @param array<string, mixed> $server
     * @return array{id: string, label: string, apiUrl: string, publicHost: string, active: bool}
     */
    private function presentServer(array $server): array
    {
        return [
            'id'         => (string) ($server['_id'] ?? ''),
            'label'      => (string) ($server['label'] ?? ''),
            'apiUrl'     => (string) ($server['apiUrl'] ?? ''),
            'publicHost' => (string) ($server['publicHost'] ?? ''),
            'active'     => (bool) ($server['active'] ?? false),
        ];
    }

    public function activate(string $id): ResponseInterface
    {
        return $this->updateActive($id, true);
    }

    public function deactivate(string $id): ResponseInterface
    {
        return $this->updateActive($id, false);
    }

    /**
     * Immediate toggle — no confirmation. SavedServersService::setActive
     * writes `active` to Cockpit and invalidates the collection cache, so
     * the next list reflects the change.
     */
    private function updateActive(string $id, bool $active): ResponseInterface
    {
        try {
            $server = Services::savedServers()->setActive($id, $active);
        } catch (\RuntimeException $e) {
            return $this->errorResponse(502, $e->getMessage());
        }

        return $this->response->setJSON($this->presentServer($server));
    }

    public function delete(string $id): ResponseInterface
    {
        return $this->response->setJSON([]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function requireString(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        return (is_string($value) && $value !== '') ? $value : null;
    }

    private function errorResponse(int $status, string $message): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => $message]);
    }
}
