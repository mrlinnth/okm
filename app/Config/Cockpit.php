<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Cockpit Configuration
 *
 * Configuration for Cockpit CMS API integration
 *
 * @package Config
 */
class Cockpit extends BaseConfig
{
    /**
     * Cockpit API base URL
     *
     * @var string
     */
    public $apiUrl = '';

    /**
     * Cockpit API token
     *
     * @var string
     */
    public $apiToken = '';

    /**
     * Default cache TTL for Cockpit data (in seconds)
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
