<?php

declare(strict_types=1);

namespace App\Controllers;

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
        return $this->response->setJSON([]);
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
        return $this->response->setJSON([]);
    }

    public function deactivate(string $id): ResponseInterface
    {
        return $this->response->setJSON([]);
    }

    public function delete(string $id): ResponseInterface
    {
        return $this->response->setJSON([]);
    }
}
