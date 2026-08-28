<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\InvalidServerJsonException;
use App\Libraries\ServerUnreachableException;
use App\Libraries\SubscriptionsService;
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

        // Auto-subscribe the server's existing Outline keys. The server
        // record is already committed, so an import failure never fails the
        // request — it is reported in the summary instead.
        try {
            $import = Services::subscriptions()->importAllFromServer(
                (string) ($server['_id'] ?? ''),
                (string) ($server['apiUrl'] ?? ''),
                SubscriptionsService::addMonthsClamped(new \DateTimeImmutable('today'), 1),
            );
        } catch (\Throwable $e) {
            $import = ['imported' => 0, 'failed' => 0, 'failures' => [['name' => '', 'error' => $e->getMessage()]]];
        }

        return $this->response->setJSON($this->presentServer($server) + ['import' => $import]);
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

    /**
     * Reconciliation diff for the Sync now modal — live Outline keys vs.
     * this server's ledger records.
     */
    public function sync(string $id): ResponseInterface
    {
        try {
            $diff = Services::savedServers()->diffServer($id);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse(404, $e->getMessage());
        } catch (\RuntimeException $e) {
            return $this->errorResponse(502, $e->getMessage());
        }

        return $this->response->setJSON($diff);
    }

    /**
     * Resolve the "found on server" section: create a subscription per
     * pasted key, honouring optional `key_name: date` lines.
     */
    public function syncImport(string $id): ResponseInterface
    {
        $body = $this->request->getJSON(true) ?? [];
        $keys = is_array($body['keys'] ?? null) ? $body['keys'] : [];
        $pastedText = is_string($body['pastedText'] ?? null) ? $body['pastedText'] : '';

        $results = Services::subscriptions()->resolveFoundOnServer($id, $keys, $pastedText);

        return $this->response->setJSON(['results' => $results]);
    }

    /**
     * Resolve one "missing on server" row: drop the stale Cockpit record
     * (its Outline key is already gone).
     */
    public function syncRemove(string $id): ResponseInterface
    {
        $body = $this->request->getJSON(true) ?? [];
        $subscriptionId = $this->requireString($body, 'subscriptionId');
        if ($subscriptionId === null) {
            return $this->errorResponse(422, 'subscriptionId is required.');
        }

        return $this->response->setJSON(['success' => Services::subscriptions()->removeRecord($subscriptionId)]);
    }

    public function delete(string $id): ResponseInterface
    {
        $subscriptionCount = Services::subscriptions()->countByServer($id);
        if ($subscriptionCount > 0) {
            return $this->errorResponse(
                422,
                "Cannot delete a server with {$subscriptionCount} active subscriptions — deactivate it instead.",
            );
        }

        $deleted = Services::savedServers()->delete($id);

        return $this->response->setJSON(['success' => $deleted]);
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
