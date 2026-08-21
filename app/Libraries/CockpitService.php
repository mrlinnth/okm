<?php

namespace App\Libraries;

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\HTTP\CURLRequest;
use Config\Services;

/**
 * CockpitService Library
 *
 * Handles Cockpit CMS Content API interactions with built-in caching
 * Uses the newer Content API endpoints (GET /content/item/*, GET /content/items/*)
 *
 * @package App\Libraries
 */
class CockpitService
{
    /**
     * Cockpit API base URL
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * Cockpit API token
     *
     * @var string
     */
    protected $apiToken;

    /**
     * CodeIgniter HTTP Client
     *
     * @var CURLRequest
     */
    protected $client;

    /**
     * Cache instance
     *
     * @var CacheInterface
     */
    protected $cache;

    /**
     * Default cache TTL (in seconds)
     *
     * @var int
     */
    protected $cacheTTL;

    /**
     * Request timeout (in seconds)
     *
     * @var int
     */
    protected $timeout;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Load configuration from Config\Cockpit
        $config = config('Cockpit');
        $this->apiUrl = rtrim($config->apiUrl, '/');
        $this->apiToken = $config->apiToken;
        $this->cacheTTL = $config->cacheTTL;
        $this->timeout = $config->timeout;

        // Initialize CodeIgniter HTTP client (no baseURI - we'll use full URLs)
        $this->client = Services::curlrequest([
            'timeout' => $this->timeout,
            'headers' => [
                'api-key' => $this->apiToken,
                'Accept' => 'application/json',
            ],
        ]);

        // Initialize cache
        $this->cache = Services::cache();
    }

    /**
     * Get a singleton resource from Cockpit (cached)
     *
     * @param string $model Name of the singleton model
     * @param int|null $ttl Cache time-to-live in seconds (null = use default)
     * @return array|null
     */
    public function getSingletonCached(string $model, ?int $ttl = null): ?array
    {
        $cacheKey = 'cockpit_singleton_' . $model;
        $ttl = $ttl ?? $this->cacheTTL;

        // Try to get from cache
        $data = $this->cache->get($cacheKey);

        if ($data !== null) {
            return $data;
        }

        // Fetch from API
        $data = $this->getSingleton($model);

        // Cache the result
        if ($data !== null) {
            $this->cache->save($cacheKey, $data, $ttl);
        }

        return $data;
    }

    /**
     * Get a collection from Cockpit (cached)
     *
     * @param string $model Name of the collection model
     * @param array $params Optional query parameters (filter, fields, sort, limit, etc.)
     * @param int|null $ttl Cache time-to-live in seconds (null = use default)
     * @return array
     */
    public function getCollectionCached(string $model, array $params = [], ?int $ttl = null): array
    {
        $cacheKey = 'cockpit_collection_' . $model . '_' . md5(json_encode($params));
        $ttl = $ttl ?? $this->cacheTTL;

        // Try to get from cache
        $data = $this->cache->get($cacheKey);

        if ($data !== null) {
            return $data;
        }

        // Fetch from API
        $data = $this->getCollection($model, $params);

        // Cache the result
        $this->cache->save($cacheKey, $data, $ttl);

        return $data;
    }

    /**
     * Get a specific item from a collection by ID (cached)
     *
     * @param string $model Name of the collection model
     * @param string $id Item ID
     * @param int|null $ttl Cache time-to-live in seconds (null = use default)
     * @return array|null
     */
    public function getItemCached(string $model, string $id, ?int $ttl = null): ?array
    {
        $cacheKey = 'cockpit_item_' . $model . '_' . $id;
        $ttl = $ttl ?? $this->cacheTTL;

        // Try to get from cache
        $data = $this->cache->get($cacheKey);

        if ($data !== null) {
            return $data;
        }

        // Fetch from API
        $data = $this->getItem($model, $id);

        // Cache the result
        if ($data !== null) {
            $this->cache->save($cacheKey, $data, $ttl);
        }

        return $data;
    }

    /**
     * Get a singleton resource from Cockpit (uncached)
     * API Endpoint: GET /content/item/{model}
     *
     * @param string $model Name of the singleton model
     * @return array|null
     */
    protected function getSingleton(string $model): ?array
    {
        try {
            $url = "{$this->apiUrl}/api/content/item/$model";
            $response = $this->client->get($url);

            if ($response->getStatusCode() === 200) {
                $body = $response->getBody();
                return json_decode($body, true);
            }

            log_message('error', "Cockpit API error for singleton model '{$model}': " . $response->getStatusCode());
            return null;
        } catch (\Exception $e) {
            log_message('error', "Cockpit API exception for singleton model '{$model}': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get a collection from Cockpit (uncached)
     * API Endpoint: GET /content/items/{model}
     *
     * @param string $model Name of the collection model
     * @param array $params Optional query parameters
     * @return array
     */
    protected function getCollection(string $model, array $params = []): array
    {
        try {
            $url = "{$this->apiUrl}/api/content/items/$model";

            $options = [];
            if (!empty($params)) {
                // Cockpit API expects filter and sort as URL-encoded JSON strings
                if (isset($params['filter']) && is_array($params['filter'])) {
                    $params['filter'] = json_encode($params['filter']);
                }
                if (isset($params['sort']) && is_array($params['sort'])) {
                    $params['sort'] = json_encode($params['sort']);
                }
                $options['query'] = $params;
            }

            $response = $this->client->get($url, $options);

            if ($response->getStatusCode() === 200) {
                $body = $response->getBody();
                $result = json_decode($body, true);

                // The Content API returns the data directly or in an 'entries' key
                if (isset($result['entries'])) {
                    return $result['entries'];
                }

                return is_array($result) ? $result : [];
            }

            log_message('error', "Cockpit API error for collection model '{$model}': " . $response->getStatusCode());
            return [];
        } catch (\Exception $e) {
            log_message('error', "Cockpit API exception for collection model '{$model}': " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a specific item from a collection by ID (uncached)
     * API Endpoint: GET /content/item/{model}/{id}
     *
     * @param string $model Name of the collection model
     * @param string $id Item ID
     * @return array|null
     */
    protected function getItem(string $model, string $id): ?array
    {
        try {
            $url = "{$this->apiUrl}/api/content/item/$model/$id";
            $response = $this->client->get($url);

            if ($response->getStatusCode() === 200) {
                $body = $response->getBody();
                return json_decode($body, true);
            }

            log_message('error', "Cockpit API error for item '{$model}/{$id}': " . $response->getStatusCode());
            return null;
        } catch (\Exception $e) {
            log_message('error', "Cockpit API exception for item '{$model}/{$id}': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Clear cache for a specific singleton
     *
     * @param string $model Name of the singleton model
     * @return bool
     */
    public function clearSingletonCache(string $model): bool
    {
        $cacheKey = 'cockpit_singleton_' . $model;
        return $this->cache->delete($cacheKey);
    }

    /**
     * Clear cache for a specific collection
     *
     * @param string $model Name of the collection model
     * @param array $params Optional query parameters used when caching
     * @return bool
     */
    public function clearCollectionCache(string $model, array $params = []): bool
    {
        $cacheKey = 'cockpit_collection_' . $model . '_' . md5(json_encode($params));
        return $this->cache->delete($cacheKey);
    }

    /**
     * Clear cache for a specific item
     *
     * @param string $model Name of the collection model
     * @param string $id Item ID
     * @return bool
     */
    public function clearItemCache(string $model, string $id): bool
    {
        $cacheKey = 'cockpit_item_' . $model . '_' . $id;
        return $this->cache->delete($cacheKey);
    }

    /**
     * Clear all Cockpit cache
     *
     * @return bool
     */
    public function clearAllCache(): bool
    {
        return $this->cache->clean();
    }

    /**
     * Set default cache TTL
     *
     * @param int $seconds
     * @return void
     */
    public function setCacheTTL(int $seconds): void
    {
        $this->cacheTTL = $seconds;
    }

    /**
     * Get the API URL
     *
     * @return string
     */
    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    /**
     * Check if Cockpit is configured
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiUrl) && !empty($this->apiToken);
    }
}
