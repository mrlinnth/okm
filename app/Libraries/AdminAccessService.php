<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Throttle\Throttler;
use Config\AdminAccess;

/**
 * Validates the shared admin password and tracks failed attempts per IP.
 */
class AdminAccessService
{
    public const AUTHENTICATED = 'authenticated';
    public const INVALID = 'invalid';
    public const THROTTLED = 'throttled';

    public function __construct(
        private readonly AdminAccess $config,
        private readonly Throttler $throttler,
    ) {
    }

    /**
     * @return self::AUTHENTICATED|self::INVALID|self::THROTTLED
     */
    public function authenticate(string $password, string $ipAddress): string
    {
        $key = 'admin-login-' . hash('sha256', $ipAddress);

        if (! $this->throttler->check($key, $this->config->maxAttempts, $this->config->throttleSeconds)) {
            return self::THROTTLED;
        }

        if ($this->config->password === '' || ! hash_equals($this->config->password, $password)) {
            return self::INVALID;
        }

        $this->throttler->remove($key);

        return self::AUTHENTICATED;
    }
}
