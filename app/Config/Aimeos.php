<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Aimeos Configuration
 *
 * Configuration for Aimeos headless e-commerce API integration
 *
 * @package Config
 */
class Aimeos extends BaseConfig
{
    /**
     * Aimeos JSON API base URL
     *
     * @var string
     */
    public $apiUrl = '';

    /**
     * Default cache TTL for Aimeos data (in seconds)
     *
     * @var int
     */
    public $cacheTTL = 3600; // 1 hour

    /**
     * HTTP timeout for API requests (in seconds)
     *
     * @var int
     */
    public $timeout = 30;
}
