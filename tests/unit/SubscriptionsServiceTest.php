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
    public function testFindByTokenUsesShortLivedFilteredCockpitLookup(): void
    {
        $cockpit = new class extends CockpitService {
            public array $arguments = [];

            public function __construct() {}

            public function getCollectionCached(string $model, array $params = [], ?int $ttl = null): array
            {
                $this->arguments = [$model, $params, $ttl];

                return [['_id' => 'sub-1', 'token' => 'recipient-token']];
            }
        };

        $subscription = (new SubscriptionsService($cockpit))->findByToken('recipient-token');

        $this->assertSame(['sub-1', 'recipient-token'], [$subscription['_id'], $subscription['token']]);
        $this->assertSame(['subscriptions', ['filter' => ['token' => 'recipient-token']], 60], $cockpit->arguments);
    }

    /**
     * @dataProvider recipientStateCases
     */
    public function testResolveRecipientStateHandlesEveryPublicPageState(?array $subscription, string $expected): void
    {
        $service = new class extends SubscriptionsService {
            protected function today(): \DateTimeImmutable { return new \DateTimeImmutable('2026-08-28'); }
        };

        $this->assertSame($expected, $service->resolveRecipientState($subscription));
    }

    /**
     * @return array<string, array{0: array<string, string>|null, 1: string}>
     */
    public static function recipientStateCases(): array
    {
        return [
            'unknown token' => [null, 'not_found'],
            'disabled subscription' => [['status' => 'disabled', 'expiryDate' => '2026-12-01'], 'disabled'],
            'active past expiry' => [['status' => 'active', 'expiryDate' => '2026-08-27'], 'expired'],
            'explicitly expired status' => [['status' => 'expired', 'expiryDate' => '2026-08-27'], 'expired'],
            'explicitly expired with a future date' => [['status' => 'expired', 'expiryDate' => '2026-12-01'], 'expired'],
            'active current expiry' => [['status' => 'active', 'expiryDate' => '2026-08-28'], 'active'],
        ];
    }

    /**
     * @dataProvider expirableCases
     */
    public function testFindExpirableSelectsOnlyActiveRecordsPastTheGracePeriod(array $subscription, bool $expected): void
    {
        $cockpit = new class($subscription) extends CockpitService {
            public function __construct(private array $subscription) {}
            public function getCollectionCached(string $model, array $params = [], ?int $ttl = null): array
            {
                return [$this->subscription];
            }
        };
        $service = new class($cockpit) extends SubscriptionsService {
            public function __construct(CockpitService $cockpit) { parent::__construct($cockpit); }
            protected function today(): \DateTimeImmutable { return new \DateTimeImmutable('2026-08-28'); }
        };

        $ids = array_column($service->findExpirable(), '_id');

        $this->assertSame($expected ? ['sub-1'] : [], $ids);
    }

    /**
     * Grace period defaults to 3 days; today is 2026-08-28, so the boundary
     * expiry date is 2026-08-25.
     *
     * @return array<string, array{0: array<string, string>, 1: bool}>
     */
    public static function expirableCases(): array
    {
        return [
            'on the grace boundary' => [['_id' => 'sub-1', 'status' => 'active', 'expiryDate' => '2026-08-25'], false],
            'one day past the boundary' => [['_id' => 'sub-1', 'status' => 'active', 'expiryDate' => '2026-08-24'], true],
            'within the grace period' => [['_id' => 'sub-1', 'status' => 'active', 'expiryDate' => '2026-08-27'], false],
            'disabled and overdue' => [['_id' => 'sub-1', 'status' => 'disabled', 'expiryDate' => '2026-01-01'], false],
            'already expired' => [['_id' => 'sub-1', 'status' => 'expired', 'expiryDate' => '2026-01-01'], false],
            'no expiry date' => [['_id' => 'sub-1', 'status' => 'active'], false],
        ];
    }

    public function testCreateFromOutlineKeyMirrorsTheKeyNameAndUsesTheGivenExpiry(): void
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

        $created = (new SubscriptionsService($cockpit))->createFromOutlineKey(
            'srv-1',
            ['id' => 'key-9', 'name' => 'manual-key', 'accessUrl' => 'ss://key-9'],
            new \DateTimeImmutable('2026-09-30'),
        );

        [$model, $data] = $cockpit->createArgs;
        $this->assertSame('subscriptions', $model);
        $this->assertSame('manual-key', $data['recipientName']);
        $this->assertSame('manual-key', $data['keyName']);
        $this->assertSame('srv-1', $data['serverId']);
        $this->assertSame('key-9', $data['outlineKeyId']);
        $this->assertSame('ss://key-9', $data['accessUrl']);
        $this->assertSame('active', $data['status']);
        $this->assertSame('2026-09-30', $data['expiryDate']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $data['token']);
        $this->assertSame('sub-1', $created['_id']);
    }

    public function testCreateFromOutlineKeyThrowsWhenCockpitWriteFails(): void
    {
        $cockpit = new class extends CockpitService {
            public function __construct() {}
            public function createItem(string $model, array $data): ?array { return null; }
        };

        $this->expectException(\RuntimeException::class);
        (new SubscriptionsService($cockpit))->createFromOutlineKey('srv-1', ['id' => 'k', 'name' => 'n'], new \DateTimeImmutable('2026-09-30'));
    }

    public function testImportAllFromServerCreatesOnePerKeyAndContinuesPastFailures(): void
    {
        $cockpit = new class extends CockpitService {
            public int $calls = 0;
            public function __construct() {}
            public function createItem(string $model, array $data): ?array
            {
                $this->calls++;

                return $data['keyName'] === 'bad' ? null : array_merge(['_id' => 'sub'], $data);
            }
        };
        $servers = new class extends SavedServersService { public function __construct() {} };
        $outline = new class extends OutlineService {
            public array $listKeysArgs = [];
            public function __construct() {}
            public function listKeys(string $apiUrl): array
            {
                $this->listKeysArgs[] = $apiUrl;

                return [
                    ['id' => 'k1', 'name' => 'alice', 'accessUrl' => 'ss://1'],
                    ['id' => 'k2', 'name' => 'bad', 'accessUrl' => 'ss://2'],
                    ['id' => 'k3', 'name' => 'carol', 'accessUrl' => 'ss://3'],
                ];
            }
        };

        $summary = (new SubscriptionsService($cockpit, $servers, $outline))
            ->importAllFromServer('srv-1', 'https://vpn.example/x', new \DateTimeImmutable('2026-09-30'));

        $this->assertSame(['https://vpn.example/x'], $outline->listKeysArgs);
        $this->assertSame(3, $cockpit->calls);
        $this->assertSame(2, $summary['imported']);
        $this->assertSame(1, $summary['failed']);
        $this->assertSame('bad', $summary['failures'][0]['name']);
    }

    public function testResolveFoundOnServerAppliesPastedDatesWithDefaultFallback(): void
    {
        $cockpit = new class extends CockpitService {
            public function __construct() {}
            public function createItem(string $model, array $data): ?array
            {
                return $data['keyName'] === 'boom' ? null : array_merge(['_id' => 'sub'], $data);
            }
        };
        $service = new class ($cockpit) extends SubscriptionsService {
            public function __construct(CockpitService $cockpit) { parent::__construct($cockpit); }
            protected function today(): \DateTimeImmutable { return new \DateTimeImmutable('2026-08-28'); }
        };

        $keys = [
            ['id' => 'k1', 'name' => 'matched', 'accessUrl' => 'ss://1'],
            ['id' => 'k2', 'name' => 'nodate', 'accessUrl' => 'ss://2'],
            ['id' => 'k3', 'name' => 'malformed', 'accessUrl' => 'ss://3'],
            ['id' => 'k4', 'name' => 'past', 'accessUrl' => 'ss://4'],
            ['id' => 'k5', 'name' => 'boom', 'accessUrl' => 'ss://5'],
        ];
        $pasted = "matched: 2026-12-01\nmalformed: not-a-date\npast: 2020-01-01\nignored line without colon\n";

        $results = $service->resolveFoundOnServer('srv-1', $keys, $pasted);
        $default = SubscriptionsService::addMonthsClamped(new \DateTimeImmutable('2026-08-28'), 1)->format('Y-m-d');

        $this->assertSame(['matched', 'resolved', '2026-12-01'], [$results[0]['name'], $results[0]['status'], $results[0]['expiryDate']]);
        $this->assertSame($default, $results[1]['expiryDate']);
        $this->assertSame($default, $results[2]['expiryDate']);
        $this->assertSame($default, $results[3]['expiryDate']);
        $this->assertSame('failed', $results[4]['status']);
        $this->assertCount(5, $results);
    }

    public function testMigrateAllToServerSuffixesCollisionsRepointsInactiveAndCleansUp(): void
    {
        $cockpit = new class extends CockpitService {
            /** @var array<string, array<string, mixed>> */
            public array $updates = [];
            public function __construct() {}
            public function getCollectionCached(string $model, array $params = [], ?int $ttl = null): array
            {
                return [
                    ['_id' => 'sub-active', 'status' => 'active', 'serverId' => 'source', 'keyName' => 'dupe', 'outlineKeyId' => 'old-1', 'recipientName' => 'Alice'],
                    ['_id' => 'sub-disabled', 'status' => 'disabled', 'serverId' => 'source', 'keyName' => 'x', 'outlineKeyId' => 'old-2', 'recipientName' => 'Bob'],
                ];
            }
            public function updateItem(string $model, string $id, array $data): ?array
            {
                $this->updates[$id] = $data;

                return array_merge(['_id' => $id], $data);
            }
        };
        $servers = new class extends SavedServersService {
            public function __construct() {}
            public function list(): array
            {
                return [
                    ['_id' => 'source', 'apiUrl' => 'https://source/api', 'active' => true],
                    ['_id' => 'dest', 'apiUrl' => 'https://dest/api', 'active' => true],
                ];
            }
        };
        $outline = new class extends OutlineService {
            /** @var array<int, array{0: string, 1: string}> */
            public array $deleted = [];
            public function __construct() {}
            public function listKeys(string $apiUrl): array { return [['id' => 'k', 'name' => 'dupe', 'accessUrl' => 'ss://k']]; }
            public function createKey(string $apiUrl, string $name): array { return ['id' => 'new-' . $name, 'accessUrl' => 'ss://' . $name, 'name' => $name, 'bytesUsed' => 0, 'usage' => '0 B']; }
            public function deleteKeyById(string $apiUrl, string $id): void { $this->deleted[] = [$apiUrl, $id]; }
        };

        $result = (new SubscriptionsService($cockpit, $servers, $outline))
            ->migrateAllToServer('source', ['_id' => 'dest', 'apiUrl' => 'https://dest/api', 'active' => true]);

        $this->assertSame('dupe_2', $cockpit->updates['sub-active']['keyName']);
        $this->assertSame('dest', $cockpit->updates['sub-active']['serverId']);
        $this->assertSame('new-dupe_2', $cockpit->updates['sub-active']['outlineKeyId']);
        $this->assertSame([['https://source/api', 'old-1']], $outline->deleted);
        $this->assertSame(['serverId' => 'dest'], $cockpit->updates['sub-disabled']);
        $this->assertSame('dupe', $result['results'][0]['renamed_from']);
        $this->assertArrayNotHasKey('warning', $result['results'][0]);
        $this->assertSame(2, $result['moved']);
        $this->assertSame(0, $result['failed']);
    }

    public function testMigrateAllToServerKeepsTheNewKeyWhenSourceCleanupFails(): void
    {
        $cockpit = new class extends CockpitService {
            public function __construct() {}
            public function getCollectionCached(string $model, array $params = [], ?int $ttl = null): array
            {
                return [['_id' => 'sub-active', 'status' => 'active', 'serverId' => 'source', 'keyName' => 'alice', 'outlineKeyId' => 'old-1', 'recipientName' => 'Alice']];
            }
            public function updateItem(string $model, string $id, array $data): ?array { return array_merge(['_id' => $id], $data); }
        };
        $servers = new class extends SavedServersService {
            public function __construct() {}
            public function list(): array
            {
                return [
                    ['_id' => 'source', 'apiUrl' => 'https://source/api', 'active' => true],
                    ['_id' => 'dest', 'apiUrl' => 'https://dest/api', 'active' => true],
                ];
            }
        };
        $outline = new class extends OutlineService {
            public function __construct() {}
            public function listKeys(string $apiUrl): array { return []; }
            public function createKey(string $apiUrl, string $name): array { return ['id' => 'new-key', 'accessUrl' => 'ss://new', 'name' => $name, 'bytesUsed' => 0, 'usage' => '0 B']; }
            public function deleteKeyById(string $apiUrl, string $id): void { throw new \RuntimeException('source unreachable'); }
        };

        $result = (new SubscriptionsService($cockpit, $servers, $outline))
            ->migrateAllToServer('source', ['_id' => 'dest', 'apiUrl' => 'https://dest/api', 'active' => true]);

        $this->assertSame('success', $result['results'][0]['status']);
        $this->assertSame('The old Outline key could not be deleted: source unreachable', $result['results'][0]['warning']);
        $this->assertSame(1, $result['moved']);
    }

    public function testRemoveRecordDeletesOnlyTheCockpitRecord(): void
    {
        $cockpit = new class extends CockpitService {
            public array $deleted = [];
            public function __construct() {}
            public function deleteItem(string $model, string $id): bool { $this->deleted = [$model, $id]; return true; }
        };

        $this->assertTrue((new SubscriptionsService($cockpit))->removeRecord('sub-1'));
        $this->assertSame(['subscriptions', 'sub-1'], $cockpit->deleted);
    }

    public function testProcessExpiryDeletesTheKeyAndMarksExpiredOnSuccess(): void
    {
        $cockpit = new class extends CockpitService {
            public array $update = [];
            public function __construct() {}
            public function updateItem(string $model, string $id, array $data): ?array { $this->update = $data; return $data; }
        };
        $servers = new class extends SavedServersService { public function __construct() {} public function list(): array { return [['_id' => 'srv-1', 'apiUrl' => 'https://outline.example/api', 'active' => true]]; } };
        $outline = new class extends OutlineService { public array $delete = []; public function __construct() {} public function deleteKey(string $apiUrl, string $name): void { $this->delete = [$apiUrl, $name]; } };

        $result = (new SubscriptionsService($cockpit, $servers, $outline))
            ->processExpiry(['_id' => 'sub-1', 'serverId' => 'srv-1', 'keyName' => 'alice-key']);

        $this->assertSame(['https://outline.example/api', 'alice-key'], $outline->delete);
        $this->assertSame(['status' => 'expired'], $cockpit->update);
        $this->assertSame(['id' => 'sub-1', 'outcome' => 'expired'], $result);
    }

    public function testProcessExpiryTreatsAnAlreadyGoneKeyAsSuccess(): void
    {
        $cockpit = new class extends CockpitService {
            public array $update = [];
            public function __construct() {}
            public function updateItem(string $model, string $id, array $data): ?array { $this->update = $data; return $data; }
        };
        $servers = new class extends SavedServersService { public function __construct() {} public function list(): array { return [['_id' => 'srv-1', 'apiUrl' => 'https://outline.example/api', 'active' => true]]; } };
        $outline = new class extends OutlineService { public function __construct() {} public function deleteKey(string $apiUrl, string $name): void { throw new \App\Libraries\OutlineRequestException('gone', notFound: true); } };

        $result = (new SubscriptionsService($cockpit, $servers, $outline))
            ->processExpiry(['_id' => 'sub-1', 'serverId' => 'srv-1', 'keyName' => 'alice-key']);

        $this->assertSame(['status' => 'expired'], $cockpit->update);
        $this->assertSame(['id' => 'sub-1', 'outcome' => 'expired'], $result);
    }

    public function testProcessExpiryLeavesTheRecordUntouchedOnAGenuineFailure(): void
    {
        $cockpit = new class extends CockpitService {
            public bool $updated = false;
            public function __construct() {}
            public function updateItem(string $model, string $id, array $data): ?array { $this->updated = true; return $data; }
        };
        $servers = new class extends SavedServersService { public function __construct() {} public function list(): array { return [['_id' => 'srv-1', 'apiUrl' => 'https://outline.example/api', 'active' => true]]; } };
        $outline = new class extends OutlineService { public function __construct() {} public function deleteKey(string $apiUrl, string $name): void { throw new \App\Libraries\OutlineRequestException('Outline request failed: connection refused'); } };

        $result = (new SubscriptionsService($cockpit, $servers, $outline))
            ->processExpiry(['_id' => 'sub-1', 'serverId' => 'srv-1', 'keyName' => 'alice-key']);

        $this->assertFalse($cockpit->updated);
        $this->assertSame('failed', $result['outcome']);
        $this->assertSame('Outline request failed: connection refused', $result['error']);
    }

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

    /**
     * @dataProvider deleteSubscriptionCases
     */
    public function testDeleteRemovesAnActiveKeyBeforeItsCockpitRecord(string $status, array $expectedEvents): void
    {
        $events = [];
        $cockpit = new class($events, $status) extends CockpitService {
            public function __construct(private array &$events, private string $status) {}
            public function getItemCached(string $model, string $id, ?int $ttl = null): ?array { return ['_id' => $id, 'status' => $this->status, 'serverId' => 'server', 'outlineKeyId' => 'key-1']; }
            public function deleteItem(string $model, string $id): bool { $this->events[] = 'cockpit'; return true; }
        };
        $servers = new class extends SavedServersService { public function __construct() {} public function list(): array { return [['_id' => 'server', 'apiUrl' => 'https://outline.example/api', 'active' => true]]; } };
        $outline = new class($events) extends OutlineService { public function __construct(private array &$events) {} public function deleteKeyById(string $apiUrl, string $id): void { $this->events[] = 'outline'; } };

        (new SubscriptionsService($cockpit, $servers, $outline))->delete('sub-1');

        $this->assertSame($expectedEvents, $events);
    }

    /** @return array<string, array{0: string, 1: array<int, string>}> */
    public static function deleteSubscriptionCases(): array
    {
        return [
            'active subscription' => ['active', ['outline', 'cockpit']],
            'disabled subscription' => ['disabled', ['cockpit']],
            'expired subscription' => ['expired', ['cockpit']],
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
