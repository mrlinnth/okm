<?php

use App\Libraries\CockpitService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class TestableCockpitService extends CockpitService
{
    /** @var array<int, array{method: string, url: string, body: ?array}> */
    public array $capturedWrites = [];

    /** @var array{status: int, body: string} */
    public array $fakeResponse = ['status' => 200, 'body' => '{}'];

    /** @var array<int, string> */
    public array $clearedCaches = [];

    protected function sendWrite(string $method, string $url, ?array $body = null): array
    {
        $this->capturedWrites[] = ['method' => $method, 'url' => $url, 'body' => $body];

        return $this->fakeResponse;
    }

    public function clearCollectionCache(string $model, array $params = []): bool
    {
        $this->clearedCaches[] = "collection:{$model}";

        return true;
    }

    public function clearItemCache(string $model, string $id): bool
    {
        $this->clearedCaches[] = "item:{$model}:{$id}";

        return true;
    }
}

/**
 * @internal
 */
final class CockpitServiceTest extends CIUnitTestCase
{
    public function testCreateItemPostsWrappedDataAndClearsCollectionCache(): void
    {
        $service = new TestableCockpitService();
        $service->fakeResponse = ['status' => 200, 'body' => json_encode(['_id' => 'abc', 'label' => 'HK-1'])];

        $result = $service->createItem('servers', ['label' => 'HK-1', 'active' => true]);

        $this->assertSame(['_id' => 'abc', 'label' => 'HK-1'], $result);
        $this->assertCount(1, $service->capturedWrites);
        $this->assertSame('POST', $service->capturedWrites[0]['method']);
        $this->assertStringEndsWith('/api/content/item/servers', $service->capturedWrites[0]['url']);
        $this->assertSame(['data' => ['label' => 'HK-1', 'active' => true]], $service->capturedWrites[0]['body']);
        $this->assertSame(['collection:servers'], $service->clearedCaches);
    }

    public function testCreateItemReturnsNullAndSkipsCacheClearOnError(): void
    {
        $service = new TestableCockpitService();
        $service->fakeResponse = ['status' => 500, 'body' => 'server error'];

        $this->assertNull($service->createItem('servers', ['label' => 'HK-1']));
        $this->assertSame([], $service->clearedCaches);
    }

    public function testUpdateItemMergesIdIntoDataAndClearsBothCaches(): void
    {
        $service = new TestableCockpitService();
        $service->fakeResponse = ['status' => 200, 'body' => json_encode(['_id' => 'abc', 'active' => false])];

        $result = $service->updateItem('servers', 'abc', ['active' => false]);

        $this->assertSame(['_id' => 'abc', 'active' => false], $result);
        $this->assertSame('POST', $service->capturedWrites[0]['method']);
        $this->assertStringEndsWith('/api/content/item/servers', $service->capturedWrites[0]['url']);
        $this->assertSame(['data' => ['active' => false, '_id' => 'abc']], $service->capturedWrites[0]['body']);
        $this->assertContains('collection:servers', $service->clearedCaches);
        $this->assertContains('item:servers:abc', $service->clearedCaches);
    }

    public function testUpdateItemReturnsNullAndSkipsCacheClearOnError(): void
    {
        $service = new TestableCockpitService();
        $service->fakeResponse = ['status' => 404, 'body' => 'not found'];

        $this->assertNull($service->updateItem('servers', 'missing', ['active' => true]));
        $this->assertSame([], $service->clearedCaches);
    }

    public function testDeleteItemSendsDeleteWithNoBodyAndClearsBothCaches(): void
    {
        $service = new TestableCockpitService();
        $service->fakeResponse = ['status' => 200, 'body' => 'true'];

        $this->assertTrue($service->deleteItem('servers', 'abc'));
        $this->assertSame('DELETE', $service->capturedWrites[0]['method']);
        $this->assertStringEndsWith('/api/content/item/servers/abc', $service->capturedWrites[0]['url']);
        $this->assertNull($service->capturedWrites[0]['body']);
        $this->assertContains('collection:servers', $service->clearedCaches);
        $this->assertContains('item:servers:abc', $service->clearedCaches);
    }

    public function testDeleteItemReturnsFalseAndSkipsCacheClearOnError(): void
    {
        $service = new TestableCockpitService();
        $service->fakeResponse = ['status' => 500, 'body' => 'server error'];

        $this->assertFalse($service->deleteItem('servers', 'abc'));
        $this->assertSame([], $service->clearedCaches);
    }

    public function testWriteMethodsNeverHardcodeAModelName(): void
    {
        $service = new TestableCockpitService();
        $service->fakeResponse = ['status' => 200, 'body' => json_encode(['_id' => '1'])];

        $service->createItem('subscriptions', ['recipient' => 'x']);
        $service->updateItem('subscriptions', '1', ['status' => 'active']);
        $service->deleteItem('subscriptions', '1');

        $this->assertStringEndsWith('/api/content/item/subscriptions', $service->capturedWrites[0]['url']);
        $this->assertStringEndsWith('/api/content/item/subscriptions', $service->capturedWrites[1]['url']);
        $this->assertStringEndsWith('/api/content/item/subscriptions/1', $service->capturedWrites[2]['url']);
    }
}
