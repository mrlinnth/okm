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

    /** @var array<int, array<int, mixed>> */
    public array $capturedCurlOptionsQueue = [];

    /** @var array{status: int, body: string, error: ?string} */
    public array $fakeResponse = ['status' => 200, 'body' => '{}', 'error' => null];

    /** @var array<int, array{status: int, body: string, error: ?string}> */
    public array $fakeResponseQueue = [];

    public function publicRequest(string $method, string $apiUrl, string $path, ?array $json = null): array
    {
        return $this->request($method, $apiUrl, $path, $json);
    }

    protected function executeCurl(array $curlOptions): array
    {
        $this->capturedCurlOptions = $curlOptions;
        $this->capturedCurlOptionsQueue[] = $curlOptions;

        if ($this->fakeResponseQueue !== []) {
            return array_shift($this->fakeResponseQueue);
        }

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

    public function testListKeysMergesAccessKeysWithTransferUsage(): void
    {
        $service = new TestableOutlineService();
        $service->fakeResponseQueue = [
            ['status' => 200, 'body' => json_encode(['accessKeys' => [
                ['id' => '1', 'name' => 'alice', 'accessUrl' => 'ss://one'],
                ['id' => '2', 'name' => 'bob', 'accessUrl' => 'ss://two'],
            ]]), 'error' => null],
            ['status' => 200, 'body' => json_encode(['bytesTransferredByUserId' => ['1' => 2048]]), 'error' => null],
        ];

        $keys = $service->listKeys('https://203.0.113.10/api');

        $this->assertSame([
            ['id' => '1', 'name' => 'alice', 'accessUrl' => 'ss://one', 'bytesUsed' => 2048, 'usage' => '2.0 KB'],
            ['id' => '2', 'name' => 'bob', 'accessUrl' => 'ss://two', 'bytesUsed' => 0, 'usage' => '0 B'],
        ], $keys);
        $this->assertStringEndsWith('/access-keys', $service->capturedCurlOptionsQueue[0][CURLOPT_URL]);
        $this->assertStringEndsWith('/metrics/transfer', $service->capturedCurlOptionsQueue[1][CURLOPT_URL]);
    }

    /**
     * @dataProvider provideFormatBytesCases
     */
    public function testFormatBytes(int $bytes, string $expected): void
    {
        $service = new TestableOutlineService();

        $this->assertSame($expected, $service->formatBytes($bytes));
    }

    public static function provideFormatBytesCases(): iterable
    {
        yield [0, '0 B'];
        yield [512, '512 B'];
        yield [2048, '2.0 KB'];
        yield [5 * 1024 * 1024, '5.0 MB'];
        yield [3 * 1024 * 1024 * 1024, '3.0 GB'];
    }
}
