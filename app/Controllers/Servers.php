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
        $servers = Services::savedServers()->list();

        return $this->render('servers.index', [
            'title'   => 'Saved Servers',
            'servers' => $servers,
        ]);
    }

    public function store(): ResponseInterface
    {
        return $this->response->setJSON([]);
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
