<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\OutlineRequestException;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Classic Key Manager Controller
 *
 * Unauthenticated Outline key management: connect to a server via its
 * exported JSON, list/create/delete/migrate keys. No local persistence —
 * every request supplies its own server credentials.
 */
class Classic extends WebController
{
    public function index(): string
    {
        return $this->render('classic.index', ['title' => 'Classic Manager']);
    }

    public function listKeys(): ResponseInterface
    {
        $body = $this->request->getJSON(true) ?? [];

        $apiUrl = $this->requireString($body, 'apiUrl');
        if ($apiUrl === null) {
            return $this->errorResponse(422, 'apiUrl is required.');
        }

        try {
            $keys = Services::outline()->listKeys($apiUrl);
        } catch (OutlineRequestException $e) {
            return $this->errorResponse(502, $e->getMessage());
        }

        return $this->response->setJSON($keys);
    }

    public function createKey(): ResponseInterface
    {
        $body = $this->request->getJSON(true) ?? [];

        $apiUrl = $this->requireString($body, 'apiUrl');
        if ($apiUrl === null) {
            return $this->errorResponse(422, 'apiUrl is required.');
        }

        $name = $this->requireString($body, 'name');
        if ($name === null) {
            return $this->errorResponse(422, 'name is required.');
        }

        try {
            $key = Services::outline()->createKey($apiUrl, $name);
        } catch (OutlineRequestException $e) {
            return $this->errorResponse(502, $e->getMessage());
        }

        return $this->response->setJSON($key);
    }

    public function deleteKey(): ResponseInterface
    {
        return $this->response->setJSON([]);
    }

    public function deleteAllKeys(): ResponseInterface
    {
        return $this->response->setJSON([]);
    }

    public function migrate(): ResponseInterface
    {
        return $this->response->setJSON([]);
    }

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
