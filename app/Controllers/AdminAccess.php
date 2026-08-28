<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AdminAccessService;
use CodeIgniter\HTTP\RedirectResponse;
use Config\Services;

/**
 * Shared-password entry point for the protected admin tools.
 */
class AdminAccess extends WebController
{
    public function index(): string|RedirectResponse
    {
        if (session()->get('adminAuthenticated') === true) {
            return redirect()->to('/subscriptions');
        }

        return $this->render('admin.manage', [
            'title' => 'Manage Subscriptions',
            'error' => session()->getFlashdata('adminLoginError'),
        ]);
    }

    public function authenticate(): RedirectResponse
    {
        $password = $this->request->getPost('password');
        $password = is_string($password) ? $password : '';

        $result = Services::adminAccess()->authenticate($password, $this->request->getIPAddress());
        if ($result !== AdminAccessService::AUTHENTICATED) {
            session()->setFlashdata('adminLoginError', 'Unable to sign in. Check the password and try again later.');

            return redirect()->to('/manage');
        }

        session()->regenerate(true);
        session()->set('adminAuthenticated', true);

        return redirect()->to('/subscriptions');
    }

    public function logout(): RedirectResponse
    {
        session()->destroy();

        return redirect()->to('/manage');
    }
}
