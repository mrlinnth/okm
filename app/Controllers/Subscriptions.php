<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Subscription ledger controller.
 *
 * Endpoint behavior is added incrementally in later subscription-ledger
 * phases; this keeps the controller and route contract available now.
 */
class Subscriptions extends WebController
{
    public function index(): string
    {
        $servers = array_values(array_filter(
            Services::savedServers()->list(),
            static fn (array $server): bool => (bool) ($server['active'] ?? false),
        ));

        return $this->render('subscriptions.index', [
            'title'         => 'Subscriptions',
            'subscriptions' => Services::subscriptions()->list(),
            'servers'       => $servers,
        ]);
    }

    public function store(): ResponseInterface
    {
        return $this->stubResponse();
    }

    public function update(string $id): ResponseInterface
    {
        return $this->stubResponse();
    }

    public function extend(string $id): ResponseInterface
    {
        return $this->stubResponse();
    }

    public function setExpiry(string $id): ResponseInterface
    {
        return $this->stubResponse();
    }

    public function enable(string $id): ResponseInterface
    {
        return $this->stubResponse();
    }

    public function disable(string $id): ResponseInterface
    {
        return $this->stubResponse();
    }

    public function reroll(string $id): ResponseInterface
    {
        return $this->stubResponse();
    }

    public function move(string $id): ResponseInterface
    {
        return $this->stubResponse();
    }

    public function delete(string $id): ResponseInterface
    {
        return $this->stubResponse();
    }

    private function stubResponse(): ResponseInterface
    {
        return $this->response->setJSON(['success' => false]);
    }
}
