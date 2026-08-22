<?php

declare(strict_types=1);

namespace App\Libraries;

use Config\Outline as OutlineConfig;

/**
 * SSRF-safe HTTP client for talking to an Outline server.
 *
 * Unlike CockpitService, this takes a target apiUrl per call — classic-mode
 * requests each supply their own server JSON, so there is no single
 * configured endpoint.
 */
class OutlineService
{
    protected OutlineConfig $config;

    public function __construct()
    {
        $this->config = config('Outline');
    }

    /**
     * Lists access keys for a server, merged with their transfer usage.
     *
     * @return array<int, array{id: string, name: string, accessUrl: string, bytesUsed: int, usage: string}>
     */
    public function listKeys(string $apiUrl): array
    {
        $keysResponse = $this->request('GET', $apiUrl, '/access-keys');
        $metricsResponse = $this->request('GET', $apiUrl, '/metrics/transfer');

        $usageByKeyId = $metricsResponse['bytesTransferredByUserId'] ?? [];

        $keys = [];

        foreach ($keysResponse['accessKeys'] ?? [] as $key) {
            $bytesUsed = (int) ($usageByKeyId[$key['id']] ?? 0);

            $keys[] = [
                'id' => (string) $key['id'],
                'name' => (string) ($key['name'] ?? ''),
                'accessUrl' => (string) ($key['accessUrl'] ?? ''),
                'bytesUsed' => $bytesUsed,
                'usage' => $this->formatBytes($bytesUsed),
            ];
        }

        return $keys;
    }

    /**
     * Formats a byte count as B/KB/MB/GB, matching the current app's display.
     */
    public function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $value = (float) $bytes;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        $formatted = $unitIndex === 0 ? (string) (int) $value : number_format($value, 1);

        return "{$formatted} {$units[$unitIndex]}";
    }

    /**
     * Send a request to an Outline server, enforcing HTTPS-only,
     * DNS-resolve-before-connect, blocked-range rejection, and IP pinning
     * (to close the DNS-rebinding window between the check and the connect).
     */
    protected function request(string $method, string $apiUrl, string $path, ?array $json = null): array
    {
        $apiUrl = rtrim($apiUrl, '/');

        if (!str_starts_with($apiUrl, 'https://')) {
            throw new OutlineRequestException('Outline API URL must use HTTPS.');
        }

        $host = parse_url($apiUrl, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            throw new OutlineRequestException('Outline API URL is malformed.');
        }

        $port = parse_url($apiUrl, PHP_URL_PORT) ?? 443;

        $ip = $this->resolveHost($host);

        if ($ip === null) {
            throw new OutlineRequestException("Unable to resolve Outline host: {$host}");
        }

        if ($this->isBlockedIp($ip)) {
            throw new OutlineRequestException("Outline host resolves to a blocked address: {$ip}");
        }

        $curlOptions = [
            CURLOPT_URL => $apiUrl . $path,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_TIMEOUT => $this->config->timeout,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        ];

        if ($json !== null) {
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($json);
        }

        $response = $this->executeCurl($curlOptions);

        if ($response['error'] !== null) {
            throw new OutlineRequestException("Outline request failed: {$response['error']}");
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new OutlineRequestException("Outline API returned HTTP {$response['status']}: {$response['body']}");
        }

        if ($response['body'] === '') {
            return [];
        }

        $decoded = json_decode($response['body'], true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Executes the curl request. Isolated from request() so tests can
     * override it with a fake transport instead of hitting the network.
     *
     * @return array{status: int, body: string, error: ?string}
     */
    protected function executeCurl(array $curlOptions): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, $curlOptions);

        $body = curl_exec($ch);
        $error = $body === false ? curl_error($ch) : null;
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return [
            'status' => $status,
            'body' => $body === false ? '' : $body,
            'error' => $error,
        ];
    }

    /**
     * Resolves a host to a single IP address, without relying on the
     * OS resolver at connect time (that lookup is pinned via CURLOPT_RESOLVE).
     */
    protected function resolveHost(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $host;
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        foreach ($records ?: [] as $record) {
            if (isset($record['ip'])) {
                return $record['ip'];
            }

            if (isset($record['ipv6'])) {
                return $record['ipv6'];
            }
        }

        $ip = gethostbyname($host);

        return $ip !== $host ? $ip : null;
    }

    protected function isBlockedIp(string $ip): bool
    {
        foreach ($this->config->blockedRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    protected function ipInRange(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr) + [1 => '0'];
        $bits = (int) $bits;

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        $remainderBits = $bits % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }

        if ($remainderBits === 0) {
            return true;
        }

        $mask = (~(0xFF >> $remainderBits)) & 0xFF;

        return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
    }
}
