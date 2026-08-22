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
        $apiUrl = $this->request->getJSON(true)['apiUrl'] ?? null;

        if (!is_string($apiUrl) || $apiUrl === '') {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'apiUrl is required.']);
        }

        try {
            $keys = Services::outline()->listKeys($apiUrl);
        } catch (OutlineRequestException $e) {
            return $this->response->setStatusCode(502)->setJSON(['error' => $e->getMessage()]);
        }

        return $this->response->setJSON($keys);
    }

    public function createKey(): ResponseInterface
    {
        return $this->response->setJSON([]);
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
}
