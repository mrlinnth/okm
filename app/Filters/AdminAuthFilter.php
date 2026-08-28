<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Requires the shared admin session for protected web and JSON routes.
 */
class AdminAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (session()->get('adminAuthenticated') === true) {
            return null;
        }

        if ($request->getMethod() !== 'GET') {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['error' => 'Admin authentication required.', 'login' => '/manage']);
        }

        return redirect()->to('/manage');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
    }
}
