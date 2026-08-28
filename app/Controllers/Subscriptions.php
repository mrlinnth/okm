<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\OutlineRequestException;
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
        $body = $this->request->getJSON(true) ?? [];

        $recipientName = $this->requireString($body, 'recipientName');
        if ($recipientName === null) {
            return $this->errorResponse(422, 'recipientName is required.');
        }

        $keyName = $this->requireString($body, 'keyName');
        if ($keyName === null) {
            return $this->errorResponse(422, 'keyName is required.');
        }

        $serverId = $this->requireString($body, 'serverId');
        if ($serverId === null) {
            return $this->errorResponse(422, 'serverId is required.');
        }

        $duration = $body['duration'] ?? null;
        if (!is_int($duration) || !in_array($duration, [1, 2, 3], true)) {
            return $this->errorResponse(422, 'duration must be 1, 2, or 3.');
        }

        $notes = $body['notes'] ?? null;
        if ($notes !== null && !is_string($notes)) {
            return $this->errorResponse(422, 'notes must be a string.');
        }

        try {
            return $this->response->setJSON(
                Services::subscriptions()->create($recipientName, $keyName, $serverId, $duration, $notes),
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse(422, $e->getMessage());
        } catch (OutlineRequestException | \RuntimeException $e) {
            return $this->errorResponse(502, $e->getMessage());
        }
    }

    public function update(string $id): ResponseInterface
    {
        $body = $this->request->getJSON(true) ?? [];
        $recipientName = $this->optionalString($body, 'recipientName');
        $keyName = $this->optionalString($body, 'keyName');

        if ($recipientName === null && $keyName === null) {
            return $this->errorResponse(422, 'recipientName or keyName is required.');
        }

        try {
            return $this->response->setJSON(Services::subscriptions()->rename($id, $recipientName, $keyName));
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse(422, $e->getMessage());
        } catch (OutlineRequestException | \RuntimeException $e) {
            return $this->errorResponse(502, $e->getMessage());
        }
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

    /**
     * @param array<string, mixed> $body
     */
    private function requireString(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        return (is_string($value) && $value !== '') ? $value : null;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function optionalString(array $body, string $key): ?string
    {
        if (!array_key_exists($key, $body)) {
            return null;
        }

        return $this->requireString($body, $key);
    }

    private function errorResponse(int $status, string $message): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => $message]);
    }
}
