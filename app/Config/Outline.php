<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Outline Configuration
 *
 * Configuration for Outline VPN server API integration
 *
 * @package Config
 */
class Outline extends BaseConfig
{
    /**
     * HTTP timeout for Outline API requests (in seconds)
     *
     * @var int
     */
    public int $timeout = 10;

    /**
     * CIDR ranges that Outline server targets are always rejected against,
     * regardless of the target host's public/private status.
     *
     * @var array<int, string>
     */
    public array $blockedRanges = [
        // IPv4
        '0.0.0.0/8',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '169.254.169.254/32',
        '224.0.0.0/4',
        '240.0.0.0/4',
        // IPv6
        '::1/128',
        'fe80::/10',
        'ff00::/8',
    ];
}
