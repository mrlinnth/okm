<?php

declare(strict_types=1);

use App\Libraries\SubscriptionsService;
use App\Libraries\CockpitService;
use App\Libraries\OutlineService;
use App\Libraries\SavedServersService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SubscriptionsServiceTest extends CIUnitTestCase
{
    public function testRenameSyncsActiveKeyBeforeUpdatingCockpit(): void
    {
        $cockpit = new class extends CockpitService {
            public array $update = [];
            public function __construct() {}
            public function getItemCached(string $model, string $id, ?int $ttl = null): ?array { return ['_id' => $id, 'status' => 'active', 'serverId' => 'srv-1', 'outlineKeyId' => 'key-1']; }
            public function updateItem(string $model, string $id, array $data): ?array { $this->update = $data; return $data; }
        };
        $servers = new class extends SavedServersService { public function __construct() {} public function list(): array { return [['_id' => 'srv-1', 'apiUrl' => 'https://outline.example/api', 'active' => true]]; } };
        $outline = new class extends OutlineService { public array $rename = []; public function __construct() {} public function renameKey(string $apiUrl, string $id, string $name): void { $this->rename = [$apiUrl, $id, $name]; } };

        (new SubscriptionsService($cockpit, $servers, $outline))->rename('sub-1', 'Alice', 'alice-key');

        $this->assertSame(['https://outline.example/api', 'key-1', 'alice-key'], $outline->rename);
        $this->assertSame(['recipientName' => 'Alice', 'keyName' => 'alice-key'], $cockpit->update);
    }

    public function testRenameDoesNotTouchOutlineForDisabledSubscription(): void
    {
        $cockpit = new class extends CockpitService { public function __construct() {} public function getItemCached(string $model, string $id, ?int $ttl = null): ?array { return ['_id' => $id, 'status' => 'disabled', 'serverId' => 'srv-1', 'outlineKeyId' => 'key-1']; } public function updateItem(string $model, string $id, array $data): ?array { return $data; } };
        $servers = new class extends SavedServersService { public function __construct() {} };
        $outline = new class extends OutlineService { public bool $called = false; public function __construct() {} public function renameKey(string $apiUrl, string $id, string $name): void { $this->called = true; } };

        (new SubscriptionsService($cockpit, $servers, $outline))->rename('sub-1', null, 'alice-key');

        $this->assertFalse($outline->called);
    }
    public function testCreatePersistsActiveSubscriptionAndShareLink(): void
    {
        $cockpit = new class extends CockpitService {
            public array $createArgs = [];
            public function __construct() {}
            public function createItem(string $model, array $data): ?array
            {
                $this->createArgs = [$model, $data];
                return array_merge(['_id' => 'sub-1'], $data);
            }
        };
        $servers = new class extends SavedServersService {
            public function __construct() {}
            public function list(): array
            {
                return [['_id' => 'srv-1', 'apiUrl' => 'https://outline.example/api', 'active' => true]];
            }
        };
        $outline = new class extends OutlineService {
            public function __construct() {}
            public function createKey(string $apiUrl, string $name): array
            {
                return ['id' => 'key-1', 'name' => $name, 'accessUrl' => 'ss://key-1', 'bytesUsed' => 0, 'usage' => '0 B'];
            }
        };
        $service = new class($cockpit, $servers, $outline) extends SubscriptionsService {
            protected function today(): \DateTimeImmutable { return new \DateTimeImmutable('2025-01-31'); }
        };

        $created = $service->create('Alice', 'alice-key', 'srv-1', 1, null);

        $this->assertSame('subscriptions', $cockpit->createArgs[0]);
        $this->assertSame('2025-02-28', $cockpit->createArgs[1]['expiryDate']);
        $this->assertSame('active', $cockpit->createArgs[1]['status']);
        $this->assertSame('key-1', $cockpit->createArgs[1]['outlineKeyId']);
        $this->assertMatchesRegularExpression('#/s/[a-f0-9]{32}$#', $created['shareLink']);
    }

    public function testListUsesCockpitCollectionAndSortsByExpiryDate(): void
    {
        $cockpit = new class extends CockpitService {
            /** @var array<int, array<string, mixed>> */
            public array $rows = [
                ['_id' => 'late', 'expiryDate' => '2026-12-01'],
                ['_id' => 'soon', 'expiryDate' => '2026-01-01'],
                ['_id' => 'middle', 'expiryDate' => '2026-06-01'],
            ];

            public function __construct()
            {
            }

            public function getCollectionCached(string $model, array $params = [], ?int $ttl = null): array
            {
                return $this->rows;
            }
        };

        $subscriptions = (new SubscriptionsService($cockpit))->list();

        $this->assertSame(['soon', 'middle', 'late'], array_column($subscriptions, '_id'));
    }

    public function testGenerateTokenIsUrlSafeAndUniqueAcrossLargeSample(): void
    {
        $tokens = [];

        for ($i = 0; $i < 10000; $i++) {
            $token = SubscriptionsService::generateToken();

            $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $token);
            $tokens[$token] = true;
        }

        $this->assertCount(10000, $tokens);
    }

    /**
     * @dataProvider addMonthsClampedCases
     */
    public function testAddMonthsClampedPreservesCalendarDates(string $from, int $months, string $expected): void
    {
        $result = SubscriptionsService::addMonthsClamped(new \DateTimeImmutable($from), $months);

        $this->assertSame($expected, $result->format('Y-m-d'));
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: string}>
     */
    public static function addMonthsClampedCases(): array
    {
        return [
            'mid-month' => ['2025-01-15', 2, '2025-03-15'],
            'non-leap-year February' => ['2025-01-31', 1, '2025-02-28'],
            'leap-year February' => ['2024-01-31', 1, '2024-02-29'],
            'thirty-first to thirty-first' => ['2025-03-31', 2, '2025-05-31'],
        ];
    }
}
