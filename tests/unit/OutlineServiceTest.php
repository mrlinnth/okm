<?php

use App\Libraries\OutlineRequestException;
use App\Libraries\OutlineService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class TestableOutlineService extends OutlineService
{
    /** @var array<int, mixed> */
    public array $capturedCurlOptions = [];

    /** @var array{status: int, body: string, error: ?string} */
    public array $fakeResponse = ['status' => 200, 'body' => '{}', 'error' => null];

    public function publicRequest(string $method, string $apiUrl, string $path, ?array $json = null): array
    {
        return $this->request($method, $apiUrl, $path, $json);
    }

    protected function executeCurl(array $curlOptions): array
    {
        $this->capturedCurlOptions = $curlOptions;

        return $this->fakeResponse;
    }
}

/**
 * @internal
 */
final class OutlineServiceTest extends CIUnitTestCase
{
    public function testRejectsNonHttpsUrls(): void
    {
        $service = new TestableOutlineService();

        $this->expectException(OutlineRequestException::class);
        $this->expectExceptionMessage('HTTPS');

        $service->publicRequest('GET', 'http://example.com', '/access-keys');
    }

    public function testRejectsBlockedRangeIp(): void
    {
        $service = new TestableOutlineService();

        $this->expectException(OutlineRequestException::class);
        $this->expectExceptionMessage('blocked address');

        // Loopback — always-blocked, and a literal IP skips DNS entirely.
        $service->publicRequest('GET', 'https://127.0.0.1:1234', '/access-keys');
    }

    public function testAttemptsWellFormedHttpsRequestPinnedToResolvedIp(): void
    {
        $service = new TestableOutlineService();
        $service->fakeResponse = ['status' => 200, 'body' => json_encode(['accessKeys' => []]), 'error' => null];

        $result = $service->publicRequest('GET', 'https://203.0.113.10:8443', '/access-keys');

        $this->assertSame([], $result['accessKeys']);
        $this->assertSame('https://203.0.113.10:8443/access-keys', $service->capturedCurlOptions[CURLOPT_URL]);
        $this->assertSame('GET', $service->capturedCurlOptions[CURLOPT_CUSTOMREQUEST]);
        $this->assertSame(['203.0.113.10:8443:203.0.113.10'], $service->capturedCurlOptions[CURLOPT_RESOLVE]);
        $this->assertFalse($service->capturedCurlOptions[CURLOPT_SSL_VERIFYPEER]);
    }
}
