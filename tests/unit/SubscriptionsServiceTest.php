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

    /**
     * @dataProvider extendCases
     */
    public function testExtendAddsOneMonthFromTheLaterOfTodayAndCurrentExpiry(string $today, string $expiry, string $expected): void
    {
        $cockpit = new class($expiry) extends CockpitService {
            public array $update = [];

            public function __construct(private string $expiry) {}
            public function getItemCached(string $model, string $id, ?int $ttl = null): ?array { return ['_id' => $id, 'expiryDate' => $this->expiry]; }
            public function updateItem(string $model, string $id, array $data): ?array { $this->update = $data; return $data; }
        };
        $service = new class($cockpit, new SavedServersService(), new OutlineService(), $today) extends SubscriptionsService {
            public function __construct(CockpitService $cockpit, SavedServersService $servers, OutlineService $outline, private string $currentDate) { parent::__construct($cockpit, $servers, $outline); }
            protected function today(): \DateTimeImmutable { return new \DateTimeImmutable($this->currentDate); }
        };

        $service->extend('sub-1');

        $this->assertSame(['expiryDate' => $expected], $cockpit->update);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function extendCases(): array
    {
        return [
            'future expiry' => ['2025-01-15', '2025-03-31', '2025-04-30'],
            'expired subscription' => ['2025-01-31', '2025-01-01', '2025-02-28'],
            'month-end expiry' => ['2024-01-01', '2024-01-31', '2024-02-29'],
        ];
    }

    public function testSetExpiryPersistsAnExactFutureDate(): void
    {
        $cockpit = new class extends CockpitService {
            public array $update = [];
            public function __construct() {}
            public function getItemCached(string $model, string $id, ?int $ttl = null): ?array { return ['_id' => $id]; }
            public function updateItem(string $model, string $id, array $data): ?array { $this->update = $data; return $data; }
        };
        $service = new class($cockpit) extends SubscriptionsService {
            public function __construct(CockpitService $cockpit) { parent::__construct($cockpit); }
            protected function today(): \DateTimeImmutable { return new \DateTimeImmutable('2026-08-28'); }
        };

        $service->setExpiry('sub-1', new \DateTimeImmutable('2026-10-15'));

        $this->assertSame(['expiryDate' => '2026-10-15'], $cockpit->update);
    }

    public function testSetExpiryRejectsPastDate(): void
    {
        $cockpit = new class extends CockpitService {
            public function __construct() {}
            public function getItemCached(string $model, string $id, ?int $ttl = null): ?array { return ['_id' => $id]; }
        };
        $service = new class($cockpit) extends SubscriptionsService {
            public function __construct(CockpitService $cockpit) { parent::__construct($cockpit); }
            protected function today(): \DateTimeImmutable { return new \DateTimeImmutable('2026-08-28'); }
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('expiryDate must be today or later.');

        $service->setExpiry('sub-1', new \DateTimeImmutable('2026-08-27'));
    }

    public function testDisableDeletesOutlineKeyBeforeMarkingSubscriptionDisabled(): void
    {
        $cockpit = new class extends CockpitService {
            public array $update = [];
            public function __construct() {}
            public function getItemCached(string $model, string $id, ?int $ttl = null): ?array { return ['_id' => $id, 'serverId' => 'srv-1', 'keyName' => 'alice-key']; }
            public function updateItem(string $model, string $id, array $data): ?array { $this->update = $data; return $data; }
        };
        $servers = new class extends SavedServersService { public function __construct() {} public function list(): array { return [['_id' => 'srv-1', 'apiUrl' => 'https://outline.example/api', 'active' => true]]; } };
        $outline = new class extends OutlineService { public array $delete = []; public function __construct() {} public function deleteKey(string $apiUrl, string $name): void { $this->delete = [$apiUrl, $name]; } };

        (new SubscriptionsService($cockpit, $servers, $outline))->disable('sub-1');

        $this->assertSame(['https://outline.example/api', 'alice-key'], $outline->delete);
        $this->assertSame(['status' => 'disabled'], $cockpit->update);
    }

    public function testEnableCreatesReplacementKeyAndPreservesExpiryDate(): void
    {
        $cockpit = new class extends CockpitService {
            public array $update = [];
            public function __construct() {}
            public function getItemCached(string $model, string $id, ?int $ttl = null): ?array { return ['_id' => $id, 'serverId' => 'srv-1', 'keyName' => 'alice-key', 'outlineKeyId' => 'old-key', 'accessUrl' => 'ss://old', 'status' => 'expired', 'expiryDate' => '2026-01-01']; }
            public function updateItem(string $model, string $id, array $data): ?array { $this->update = $data; return $data; }
        };
        $servers = new class extends SavedServersService { public function __construct() {} public function list(): array { return [['_id' => 'srv-1', 'apiUrl' => 'https://outline.example/api', 'active' => true]]; } };
        $outline = new class extends OutlineService { public array $create = []; public function __construct() {} public function createKey(string $apiUrl, string $name): array { $this->create = [$apiUrl, $name]; return ['id' => 'new-key', 'accessUrl' => 'ss://new', 'name' => $name, 'bytesUsed' => 0, 'usage' => '0 B']; } };

        (new SubscriptionsService($cockpit, $servers, $outline))->enable('sub-1');

        $this->assertSame(['https://outline.example/api', 'alice-key'], $outline->create);
        $this->assertSame(['outlineKeyId' => 'new-key', 'accessUrl' => 'ss://new', 'status' => 'active'], $cockpit->update);
        $this->assertArrayNotHasKey('expiryDate', $cockpit->update);
    }

    public function testReplaceKeyRecordsTheNewKeyBeforeDeletingTheOldOne(): void
    {
        $events = [];
        $cockpit = new class($events) extends CockpitService {
            public function __construct(private array &$events) {}
            public function updateItem(string $model, string $id, array $data): ?array { $this->events[] = 'update'; return array_merge(['_id' => $id], $data); }
        };
        $servers = new class extends SavedServersService { public function __construct() {} public function list(): array { return [['_id' => 'source', 'apiUrl' => 'https://source.example/api', 'active' => true], ['_id' => 'destination', 'apiUrl' => 'https://destination.example/api', 'active' => true]]; } };
        $outline = new class($events) extends OutlineService {
            public function __construct(private array &$events) {}
            public function createKey(string $apiUrl, string $name): array { $this->events[] = 'create'; return ['id' => 'new-key', 'accessUrl' => 'ss://new', 'name' => $name, 'bytesUsed' => 0, 'usage' => '0 B']; }
            public function deleteKeyById(string $apiUrl, string $id): void { $this->events[] = 'delete'; }
        };
        $service = new class($cockpit, $servers, $outline) extends SubscriptionsService {
            public function replace(array $subscription, string $targetServerId): array { return $this->replaceKey($subscription, $targetServerId); }
        };

        $result = $service->replace(['_id' => 'sub-1', 'serverId' => 'source', 'outlineKeyId' => 'old-key', 'keyName' => 'alice-key'], 'destination');

        $this->assertSame(['create', 'update', 'delete'], $events);
        $this->assertSame('destination', $result['serverId']);
        $this->assertArrayNotHasKey('warning', $result);
    }

    public function testReplaceKeyReturnsWarningWhenOldKeyCleanupFails(): void
    {
        $cockpit = new class extends CockpitService {
            public function __construct() {}
            public function updateItem(string $model, string $id, array $data): ?array { return array_merge(['_id' => $id], $data); }
        };
        $servers = new class extends SavedServersService { public function __construct() {} public function list(): array { return [['_id' => 'source', 'apiUrl' => 'https://source.example/api', 'active' => true]]; } };
        $outline = new class extends OutlineService {
            public function __construct() {}
            public function createKey(string $apiUrl, string $name): array { return ['id' => 'new-key', 'accessUrl' => 'ss://new', 'name' => $name, 'bytesUsed' => 0, 'usage' => '0 B']; }
            public function deleteKeyById(string $apiUrl, string $id): void { throw new \RuntimeException('source unavailable'); }
        };
        $service = new class($cockpit, $servers, $outline) extends SubscriptionsService {
            public function replace(array $subscription, string $targetServerId): array { return $this->replaceKey($subscription, $targetServerId); }
        };

        $result = $service->replace(['_id' => 'sub-1', 'serverId' => 'source', 'outlineKeyId' => 'old-key', 'keyName' => 'alice-key'], 'source');

        $this->assertSame('new-key', $result['outlineKeyId']);
        $this->assertSame('The old Outline key could not be deleted: source unavailable', $result['warning']);
    }

    public function testRerollReplacesAnActiveSubscriptionKeyOnTheSameServer(): void
    {
        $cockpit = new class extends CockpitService {
            public function __construct() {}
            public function getItemCached(string $model, string $id, ?int $ttl = null): ?array { return ['_id' => $id, 'status' => 'active', 'serverId' => 'server', 'outlineKeyId' => 'old-key', 'keyName' => 'alice-key']; }
            public function updateItem(string $model, string $id, array $data): ?array { return array_merge(['_id' => $id], $data); }
        };
        $servers = new class extends SavedServersService { public function __construct() {} public function list(): array { return [['_id' => 'server', 'apiUrl' => 'https://outline.example/api', 'active' => true]]; } };
        $outline = new class extends OutlineService {
            public function __construct() {}
            public function createKey(string $apiUrl, string $name): array { return ['id' => 'new-key', 'accessUrl' => 'ss://new', 'name' => $name, 'bytesUsed' => 0, 'usage' => '0 B']; }
            public function deleteKeyById(string $apiUrl, string $id): void {}
        };

        $result = (new SubscriptionsService($cockpit, $servers, $outline))->reroll('sub-1');

        $this->assertSame('new-key', $result['outlineKeyId']);
        $this->assertArrayNotHasKey('serverId', $result);
    }

    public function testRerollRejectsNonActiveSubscription(): void
    {
        $cockpit = new class extends CockpitService {
            public function __construct() {}
            public function getItemCached(string $model, string $id, ?int $ttl = null): ?array { return ['_id' => $id, 'status' => 'disabled']; }
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only active subscriptions can reroll their key.');
        (new SubscriptionsService($cockpit))->reroll('sub-1');
    }

    public function testMoveReplacesKeyOnAValidActiveDestination(): void
    {
        $cockpit = new class extends CockpitService {
            public function __construct() {}
            public function getItemCached(string $model, string $id, ?int $ttl = null): ?array { return ['_id' => $id, 'serverId' => 'source', 'outlineKeyId' => 'old-key', 'keyName' => 'alice-key']; }
            public function updateItem(string $model, string $id, array $data): ?array { return array_merge(['_id' => $id], $data); }
        };
        $servers = new class extends SavedServersService { public function __construct() {} public function list(): array { return [['_id' => 'source', 'apiUrl' => 'https://source.example/api', 'active' => true], ['_id' => 'destination', 'apiUrl' => 'https://destination.example/api', 'active' => true]]; } };
        $outline = new class extends OutlineService {
            public function __construct() {}
            public function createKey(string $apiUrl, string $name): array { return ['id' => 'new-key', 'accessUrl' => 'ss://new', 'name' => $name, 'bytesUsed' => 0, 'usage' => '0 B']; }
            public function deleteKeyById(string $apiUrl, string $id): void {}
        };

        $result = (new SubscriptionsService($cockpit, $servers, $outline))->move('sub-1', 'destination');

        $this->assertSame('destination', $result['serverId']);
        $this->assertSame('new-key', $result['outlineKeyId']);
    }

    /**
     * @dataProvider invalidMoveDestinations
     */
    public function testMoveRejectsAnInvalidDestination(string $destinationServerId, string $message): void
    {
        $cockpit = new class extends CockpitService {
            public function __construct() {}
            public function getItemCached(string $model, string $id, ?int $ttl = null): ?array { return ['_id' => $id, 'serverId' => 'source']; }
        };
        $servers = new class extends SavedServersService { public function __construct() {} public function list(): array { return [['_id' => 'source', 'apiUrl' => 'https://source.example/api', 'active' => true], ['_id' => 'inactive', 'apiUrl' => 'https://inactive.example/api', 'active' => false]]; } };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        (new SubscriptionsService($cockpit, $servers))->move('sub-1', $destinationServerId);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function invalidMoveDestinations(): array
    {
        return [
            'same server' => ['source', 'The destination server must differ from the current server.'],
            'inactive server' => ['inactive', 'The selected server is inactive.'],
            'missing server' => ['missing', 'The selected server was not found.'],
        ];
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
