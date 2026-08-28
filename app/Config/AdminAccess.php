<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Shared-secret access configuration for the admin-only areas.
 */
class AdminAccess extends BaseConfig
{
    /**
     * Set with adminaccess.password in .env. An empty value fails closed.
     */
    public string $password = '';

    public int $maxAttempts = 5;

    public int $throttleSeconds = 900;
}
