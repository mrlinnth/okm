<?php

namespace App\Libraries;

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\HTTP\CURLRequest;
use Config\Services;

/**
 * AimeosService Library
 *
 * Handles Aimeos headless e-commerce JSON API interactions with built-in caching
 * Uses the JSON:API format (https://jsonapi.org/)
 *
 * @package App\Libraries
 */
class AimeosService
{
    /**
     * Aimeos JSON API base URL
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * Aimeos site base URL (for constructing image URLs)
     *
     * @var string
     */
    protected $siteUrl;

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
        // Load configuration from Config\Aimeos
        $config = config('Aimeos');
        $this->apiUrl = rtrim($config->apiUrl, '/');
        $this->cacheTTL = $config->cacheTTL;
        $this->timeout = $config->timeout;

        // Derive site base URL from API URL (remove /jsonapi suffix)
        $this->siteUrl = preg_replace('#/jsonapi$#', '', $this->apiUrl);

        // Initialize CodeIgniter HTTP client
        $this->client = Services::curlrequest([
            'timeout' => $this->timeout,
            'headers' => [
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
            ],
        ]);

        // Initialize cache
        $this->cache = Services::cache();
    }

    /**
     * Get all products (cached)
     *
     * @param array $params Optional query parameters (filter, sort, page, etc.)
     * @param int|null $ttl Cache time-to-live in seconds (null = use default)
     * @return array
     */
    public function getProductsCached(array $params = [], ?int $ttl = null): array
    {
        $cacheKey = 'aimeos_products_' . md5(json_encode($params));
        $ttl = $ttl ?? $this->cacheTTL;

        // Try to get from cache
        $data = $this->cache->get($cacheKey);

        if ($data !== null) {
            return $data;
        }

        // Fetch from API
        $data = $this->getProducts($params);

        // Cache the result
        $this->cache->save($cacheKey, $data, $ttl);

        return $data;
    }

    /**
     * Get a single product by ID (cached)
     *
     * @param string $id Product ID
     * @param int|null $ttl Cache time-to-live in seconds (null = use default)
     * @return array|null
     */
    public function getProductCached(string $id, ?int $ttl = null): ?array
    {
        $cacheKey = 'aimeos_product_' . $id;
        $ttl = $ttl ?? $this->cacheTTL;

        // Try to get from cache
        $data = $this->cache->get($cacheKey);

        if ($data !== null) {
            return $data;
        }

        // Fetch from API
        $data = $this->getProduct($id);

        // Cache the result
        if ($data !== null) {
            $this->cache->save($cacheKey, $data, $ttl);
        }

        return $data;
    }

    /**
     * Get all products (uncached)
     * API Endpoint: GET /jsonapi/product?include=media
     *
     * @param array $params Optional query parameters
     * @return array
     */
    protected function getProducts(array $params = []): array
    {
        try {
            $url = "{$this->apiUrl}/product";

            // Always include media for product images
            $params['include'] = 'media';

            $options = ['query' => $params];

            $response = $this->client->get($url, $options);

            if ($response->getStatusCode() === 200) {
                $body = $response->getBody();
                $result = json_decode($body, true);

                // JSON:API returns data in 'data' key as an array
                if (isset($result['data']) && is_array($result['data'])) {
                    $included = $result['included'] ?? [];
                    $meta = $result['meta'] ?? [];
                    return $this->normalizeProducts($result['data'], $included, $meta);
                }

                return [];
            }

            log_message('error', "Aimeos API error for products: " . $response->getStatusCode());
            return [];
        } catch (\Exception $e) {
            log_message('error', "Aimeos API exception for products: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a single product by ID (uncached)
     * API Endpoint: GET /jsonapi/product?id={id}&include=media
     *
     * @param string $id Product ID
     * @return array|null
     */
    protected function getProduct(string $id): ?array
    {
        try {
            $url = "{$this->apiUrl}/product";
            $options = [
                'query' => [
                    'id' => $id,
                    'include' => 'media',
                ],
            ];

            $response = $this->client->get($url, $options);

            if ($response->getStatusCode() === 200) {
                $body = $response->getBody();
                $result = json_decode($body, true);

                // JSON:API returns single item in 'data' key as an object
                if (isset($result['data'])) {
                    $included = $result['included'] ?? [];
                    $meta = $result['meta'] ?? [];
                    return $this->normalizeProduct($result['data'], $included, $meta);
                }

                return null;
            }

            log_message('error', "Aimeos API error for product '{$id}': " . $response->getStatusCode());
            return null;
        } catch (\Exception $e) {
            log_message('error', "Aimeos API exception for product '{$id}': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Normalize a list of products from JSON:API format
     *
     * @param array $products Raw products array from JSON:API
     * @param array $included Included resources (media, etc.)
     * @param array $meta Response metadata
     * @return array Normalized products
     */
    protected function normalizeProducts(array $products, array $included = [], array $meta = []): array
    {
        $mediaMap = $this->buildMediaMap($included, $meta);

        return array_map(function ($product) use ($mediaMap) {
            return $this->normalizeProduct($product, [], [], $mediaMap);
        }, $products);
    }

    /**
     * Build a map of media items from included array
     *
     * @param array $included Included resources
     * @param array $meta Response metadata
     * @return array Media map indexed by ID
     */
    protected function buildMediaMap(array $included, array $meta): array
    {
        $mediaBaseUrl = $meta['content-baseurls']['fs-media'] ?? '/aimeos';
        $mediaMap = [];

        foreach ($included as $item) {
            if (($item['type'] ?? '') === 'media') {
                $id = $item['id'] ?? '';
                $attrs = $item['attributes'] ?? [];
                $mediaUrl = $attrs['media.url'] ?? '';

                if ($id && $mediaUrl) {
                    // Check if URL is already absolute
                    if (preg_match('#^https?://#', $mediaUrl)) {
                        $fullUrl = $mediaUrl;
                    } else {
                        // Construct full image URL for relative paths
                        $fullUrl = $this->siteUrl . $mediaBaseUrl . '/' . ltrim($mediaUrl, '/');
                    }

                    // Use preview URL if available (better for thumbnails)
                    $previewUrl = $attrs['media.preview'] ?? '';
                    if ($previewUrl && !preg_match('#^https?://#', $previewUrl)) {
                        $previewUrl = $this->siteUrl . $mediaBaseUrl . '/' . ltrim($previewUrl, '/');
                    }

                    $mediaMap[$id] = [
                        'id' => $id,
                        'url' => $fullUrl,
                        'preview' => $previewUrl ?: $fullUrl,
                        'type' => $attrs['media.type'] ?? 'default',
                        'label' => $attrs['media.label'] ?? '',
                        'mimetype' => $attrs['media.mimetype'] ?? '',
                    ];
                }
            }
        }

        return $mediaMap;
    }

    /**
     * Normalize a single product from JSON:API format
     * Flattens the nested attributes structure for easier access in views
     *
     * @param array $product Raw product from JSON:API
     * @param array $included Included resources (used for single product fetch)
     * @param array $meta Response metadata (used for single product fetch)
     * @param array $mediaMap Pre-built media map (used when normalizing multiple products)
     * @return array Normalized product
     */
    protected function normalizeProduct(array $product, array $included = [], array $meta = [], array $mediaMap = []): array
    {
        $attributes = $product['attributes'] ?? [];

        // Build media map if not provided (single product fetch)
        if (empty($mediaMap) && !empty($included)) {
            $mediaMap = $this->buildMediaMap($included, $meta);
        }

        // Extract product images from relationships
        $images = $this->extractProductImages($product, $mediaMap);

        return [
            'id' => $product['id'] ?? null,
            'type' => $product['type'] ?? 'product',
            'label' => $attributes['product.label'] ?? '',
            'code' => $attributes['product.code'] ?? '',
            'url' => $attributes['product.url'] ?? '',
            'productType' => $attributes['product.type'] ?? 'default',
            'status' => $attributes['product.status'] ?? 0,
            'rating' => $attributes['product.rating'] ?? '0.00',
            'ratings' => $attributes['product.ratings'] ?? 0,
            'instock' => $attributes['product.instock'] ?? 0,
            'datestart' => $attributes['product.datestart'] ?? null,
            'dateend' => $attributes['product.dateend'] ?? null,
            'images' => $images,
            'links' => $product['links'] ?? [],
            'raw' => $attributes, // Keep raw attributes for advanced usage
        ];
    }

    /**
     * Extract product images from relationships and media map
     *
     * @param array $product Raw product data
     * @param array $mediaMap Media map indexed by ID
     * @return array Array of image data
     */
    protected function extractProductImages(array $product, array $mediaMap): array
    {
        $images = [];
        $relationships = $product['relationships'] ?? [];

        // Check for media relationship
        $mediaRel = $relationships['media']['data'] ?? [];

        foreach ($mediaRel as $mediaRef) {
            $mediaId = $mediaRef['id'] ?? '';
            if ($mediaId && isset($mediaMap[$mediaId])) {
                $images[] = $mediaMap[$mediaId];
            }
        }

        return $images;
    }

    /**
     * Clear cache for products list
     *
     * @param array $params Optional query parameters used when caching
     * @return bool
     */
    public function clearProductsCache(array $params = []): bool
    {
        $cacheKey = 'aimeos_products_' . md5(json_encode($params));
        return $this->cache->delete($cacheKey);
    }

    /**
     * Clear cache for a specific product
     *
     * @param string $id Product ID
     * @return bool
     */
    public function clearProductCache(string $id): bool
    {
        $cacheKey = 'aimeos_product_' . $id;
        return $this->cache->delete($cacheKey);
    }

    /**
     * Clear all Aimeos cache
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
     * Check if Aimeos is configured
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiUrl);
    }
}
