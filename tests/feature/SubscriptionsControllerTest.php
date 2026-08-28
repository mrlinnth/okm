<?php

declare(strict_types=1);

use App\Libraries\SavedServersService;
use App\Libraries\SubscriptionsService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * @internal
 */
final class SubscriptionsControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private SubscriptionsService $subscriptions;
    private SavedServersService $servers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptions = new class extends SubscriptionsService {
            public int $listCalls = 0;
            public array $createArgs = [];
            public array $renameArgs = [];

            public function __construct()
            {
            }

            public function list(): array
            {
                $this->listCalls++;

                return [];
            }

            public function create(string $recipientName, string $keyName, string $serverId, int $durationMonths, ?string $notes): array
            {
                $this->createArgs = [$recipientName, $keyName, $serverId, $durationMonths, $notes];

                return ['_id' => 'sub-1', 'status' => 'active', 'shareLink' => 'http://localhost/s/token'];
            }

            public function rename(string $id, ?string $recipientName, ?string $keyName): array
            {
                $this->renameArgs = [$id, $recipientName, $keyName];

                return ['_id' => $id, 'recipientName' => $recipientName, 'keyName' => $keyName];
            }
        };
        $this->servers = new class extends SavedServersService {
            public function __construct()
            {
            }

            public function list(): array
            {
                return [
                    ['_id' => 'active', 'active' => true],
                    ['_id' => 'inactive', 'active' => false],
                ];
            }
        };

        Services::injectMock('subscriptions', $this->subscriptions);
        Services::injectMock('savedServers', $this->servers);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Services::reset();
    }

    public function testIndexReturnsOk(): void
    {
        $this->get('/subscriptions')->assertStatus(200);
        $this->assertSame(1, $this->subscriptions->listCalls);
    }

    public function testStoreCreatesSubscriptionFromValidInput(): void
    {
        $result = $this->withBodyFormat('json')->post('/subscriptions', [
            'recipientName' => 'Alice', 'keyName' => 'alice-key', 'serverId' => 'srv-1', 'duration' => 2,
        ]);

        $result->assertStatus(200);
        $this->assertSame(['Alice', 'alice-key', 'srv-1', 2, null], $this->subscriptions->createArgs);
        $result->assertJSONFragment(['status' => 'active']);
    }

    public function testStoreRejectsInvalidDuration(): void
    {
        $result = $this->withBodyFormat('json')->post('/subscriptions', [
            'recipientName' => 'Alice', 'keyName' => 'alice-key', 'serverId' => 'srv-1', 'duration' => 4,
        ]);

        $result->assertStatus(422);
        $this->assertSame([], $this->subscriptions->createArgs);
    }

    public function testUpdatePassesOptionalNamesToSubscriptionService(): void
    {
        $result = $this->withBodyFormat('json')->post('/subscriptions/sub-1', [
            'recipientName' => 'Alice Updated', 'keyName' => 'alice-key-updated',
        ]);

        $result->assertStatus(200);
        $this->assertSame(['sub-1', 'Alice Updated', 'alice-key-updated'], $this->subscriptions->renameArgs);
    }
}
