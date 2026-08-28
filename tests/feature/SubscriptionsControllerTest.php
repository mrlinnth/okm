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
            public array $extendArgs = [];
            public array $setExpiryArgs = [];
            public array $enableArgs = [];
            public array $disableArgs = [];
            public array $rerollArgs = [];

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

            public function extend(string $id): array
            {
                $this->extendArgs = [$id];

                return ['_id' => $id, 'expiryDate' => '2026-09-28'];
            }

            public function setExpiry(string $id, \DateTimeImmutable $date): array
            {
                $this->setExpiryArgs = [$id, $date->format('Y-m-d')];

                return ['_id' => $id, 'expiryDate' => $date->format('Y-m-d')];
            }

            public function enable(string $id): array
            {
                $this->enableArgs = [$id];

                return ['_id' => $id, 'status' => 'active'];
            }

            public function disable(string $id): array
            {
                $this->disableArgs = [$id];

                return ['_id' => $id, 'status' => 'disabled'];
            }

            public function reroll(string $id): array
            {
                $this->rerollArgs = [$id];

                return ['_id' => $id, 'outlineKeyId' => 'new-key', 'accessUrl' => 'ss://new'];
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

    public function testExtendPassesSubscriptionToService(): void
    {
        $result = $this->post('/subscriptions/sub-1/extend');

        $result->assertStatus(200);
        $this->assertSame(['sub-1'], $this->subscriptions->extendArgs);
        $result->assertJSONFragment(['expiryDate' => '2026-09-28']);
    }

    public function testSetExpiryPassesValidDateToSubscriptionService(): void
    {
        $result = $this->withBodyFormat('json')->post('/subscriptions/sub-1/expiry', ['date' => '2026-09-15']);

        $result->assertStatus(200);
        $this->assertSame(['sub-1', '2026-09-15'], $this->subscriptions->setExpiryArgs);
    }

    public function testSetExpiryRejectsInvalidDate(): void
    {
        $result = $this->withBodyFormat('json')->post('/subscriptions/sub-1/expiry', ['date' => '2026-09-31']);

        $result->assertStatus(422);
        $this->assertSame([], $this->subscriptions->setExpiryArgs);
    }

    public function testEnablePassesSubscriptionToService(): void
    {
        $result = $this->post('/subscriptions/sub-1/enable');

        $result->assertStatus(200);
        $this->assertSame(['sub-1'], $this->subscriptions->enableArgs);
        $result->assertJSONFragment(['status' => 'active']);
    }

    public function testDisablePassesSubscriptionToService(): void
    {
        $result = $this->post('/subscriptions/sub-1/disable');

        $result->assertStatus(200);
        $this->assertSame(['sub-1'], $this->subscriptions->disableArgs);
        $result->assertJSONFragment(['status' => 'disabled']);
    }

    public function testRerollPassesSubscriptionToService(): void
    {
        $result = $this->post('/subscriptions/sub-1/reroll');

        $result->assertStatus(200);
        $this->assertSame(['sub-1'], $this->subscriptions->rerollArgs);
        $result->assertJSONFragment(['outlineKeyId' => 'new-key']);
    }
}
