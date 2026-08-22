<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

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
        return $this->response->setJSON([]);
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
