<?php

use App\Libraries\CockpitService;
use App\Libraries\InvalidServerJsonException;
use App\Libraries\OutlineRequestException;
use App\Libraries\OutlineService;
use App\Libraries\SavedServersService;
use App\Libraries\ServerUnreachableException;
use App\Libraries\SubscriptionsService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class FakeOutlineForSavedServers extends OutlineService
{
    public bool $reachable = true;

    /** @var array<int, string> */
    public array $listKeysCalledWith = [];

    /** @var array<int, array<string, mixed>> */
    public array $keys = [];

    public function listKeys(string $apiUrl): array
    {
        $this->listKeysCalledWith[] = $apiUrl;

        if (!$this->reachable) {
            throw new OutlineRequestException('Outline request failed: could not connect');
        }

        return $this->keys;
    }
}

/**
 * @internal
 */
final class FakeCockpitForSavedServers extends CockpitService
{
    /** @var array<int, array{0: string, 1: array}> */
    public array $createCalls = [];

    /** @var array<int, array{0: string, 1: string, 2: array}> */
    public array $updateCalls = [];

    /** @var array<int, array{0: string, 1: string}> */
    public array $deleteCalls = [];

    /** @var array<int, array<string, mixed>> */
    public array $collection = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    public array $collections = [];

    public bool $createFails = false;

    public function createItem(string $model, array $data): ?array
    {
        $this->createCalls[] = [$model, $data];

        if ($this->createFails) {
            return null;
        }

        return array_merge(['_id' => 'generated-id'], $data);
    }

    public function updateItem(string $model, string $id, array $data): ?array
    {
        $this->updateCalls[] = [$model, $id, $data];

        return array_merge(['_id' => $id], $data);
    }

    public function deleteItem(string $model, string $id): bool
    {
        $this->deleteCalls[] = [$model, $id];

        return true;
    }

    public function getCollectionCached(string $model, array $params = [], ?int $ttl = null): array
    {
        return $this->collections[$model] ?? $this->collection;
    }
}

/**
 * @internal
 */
final class SavedServersServiceTest extends CIUnitTestCase
{
    private FakeCockpitForSavedServers $cockpit;
    private FakeOutlineForSavedServers $outline;
    private SavedServersService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cockpit = new FakeCockpitForSavedServers();
        $this->outline = new FakeOutlineForSavedServers();
        $this->service = new SavedServersService($this->cockpit, $this->outline);
    }

    public function testParseServerJsonReturnsDecodedArrayForValidInput(): void
    {
        $parsed = $this->service->parseServerJson('{"apiUrl":"https://example.com:8443/x","certSha256":"AB"}');

        $this->assertSame('https://example.com:8443/x', $parsed['apiUrl']);
        $this->assertSame('AB', $parsed['certSha256']);
    }

    public function testParseServerJsonThrowsOnUnparseableJson(): void
    {
        $this->expectException(InvalidServerJsonException::class);

        $this->service->parseServerJson('not json');
    }

    public function testParseServerJsonThrowsWhenApiUrlMissing(): void
    {
        $this->expectException(InvalidServerJsonException::class);

        $this->service->parseServerJson('{"certSha256":"AB"}');
    }

    public function testParseServerJsonThrowsWhenApiUrlNotHttps(): void
    {
        $this->expectException(InvalidServerJsonException::class);

        $this->service->parseServerJson('{"apiUrl":"http://example.com/x"}');
    }

    public function testCheckReachableIsTrueWhenOutlineListsKeys(): void
    {
        $this->assertTrue($this->service->checkReachable('https://example.com/x'));
        $this->assertSame(['https://example.com/x'], $this->outline->listKeysCalledWith);
    }

    public function testCheckReachableIsFalseWhenOutlineThrows(): void
    {
        $this->outline->reachable = false;

        $this->assertFalse($this->service->checkReachable('https://example.com/x'));
    }

    public function testCreatePersistsServerWithDerivedApiUrlOnSuccess(): void
    {
        $json = '{"apiUrl":"https://vpn.example.com:8443/secret","certSha256":"AB"}';

        $item = $this->service->create('HK-1', $json, 'vpn.public.example.com');

        $this->assertCount(1, $this->cockpit->createCalls);
        [$model, $data] = $this->cockpit->createCalls[0];
        $this->assertSame('servers', $model);
        $this->assertSame('HK-1', $data['label']);
        $this->assertSame($json, $data['serverJson']);
        $this->assertSame('https://vpn.example.com:8443/secret', $data['apiUrl']);
        $this->assertSame('vpn.public.example.com', $data['publicHost']);
        $this->assertTrue($data['active']);
        $this->assertSame('generated-id', $item['_id']);
    }

    public function testCreateStoresEmptyPublicHostWhenNoneGiven(): void
    {
        $this->service->create('HK-1', '{"apiUrl":"https://a.example.com/x"}', null);

        $this->assertSame('', $this->cockpit->createCalls[0][1]['publicHost']);
    }

    public function testCreateShortCircuitsBeforeCockpitWriteOnInvalidJson(): void
    {
        try {
            $this->service->create('HK-1', 'not json', null);
            $this->fail('expected InvalidServerJsonException');
        } catch (InvalidServerJsonException $e) {
            // expected
        }

        $this->assertSame([], $this->cockpit->createCalls);
        $this->assertSame([], $this->outline->listKeysCalledWith);
    }

    public function testCreateShortCircuitsBeforeCockpitWriteWhenUnreachable(): void
    {
        $this->outline->reachable = false;

        try {
            $this->service->create('HK-1', '{"apiUrl":"https://a.example.com/x"}', null);
            $this->fail('expected ServerUnreachableException');
        } catch (ServerUnreachableException $e) {
            // expected
        }

        $this->assertSame([], $this->cockpit->createCalls);
    }

    public function testCreateThrowsWhenCockpitWriteFails(): void
    {
        $this->cockpit->createFails = true;

        $this->expectException(\RuntimeException::class);

        $this->service->create('HK-1', '{"apiUrl":"https://a.example.com/x"}', null);
    }

    public function testSetActiveDelegatesToUpdateItem(): void
    {
        $item = $this->service->setActive('srv-9', false);

        $this->assertSame(['servers', 'srv-9', ['active' => false]], $this->cockpit->updateCalls[0]);
        $this->assertFalse($item['active']);
    }

    public function testDeleteDelegatesToDeleteItem(): void
    {
        $this->assertTrue($this->service->delete('srv-9'));
        $this->assertSame(['servers', 'srv-9'], $this->cockpit->deleteCalls[0]);
    }

    public function testListDelegatesToGetCollectionCached(): void
    {
        $this->cockpit->collection = [['_id' => '1', 'label' => 'A']];

        $this->assertSame([['_id' => '1', 'label' => 'A']], $this->service->list());
    }

    public function testDiffServerCategorizesLiveKeysAndLedgerRecords(): void
    {
        $this->cockpit->collections['servers'] = [
            ['_id' => 'srv-1', 'apiUrl' => 'https://vpn.example.com/x'],
        ];
        $this->cockpit->collections['subscriptions'] = [
            ['_id' => 'sub-synced', 'serverId' => 'srv-1', 'outlineKeyId' => 'key-synced'],
            ['_id' => 'sub-orphan', 'serverId' => 'srv-1', 'outlineKeyId' => 'key-gone'],
        ];
        $this->outline->keys = [
            ['id' => 'key-synced', 'name' => 'alice', 'accessUrl' => 'ss://synced'],
            ['id' => 'key-extra', 'name' => 'manual-key', 'accessUrl' => 'ss://extra'],
        ];

        $diff = $this->service->diffServer('srv-1');

        $this->assertSame(['https://vpn.example.com/x'], $this->outline->listKeysCalledWith);
        $this->assertSame(
            [['id' => 'key-extra', 'name' => 'manual-key', 'accessUrl' => 'ss://extra']],
            $diff['foundOnServer'],
        );
        $this->assertSame(['sub-orphan'], array_column($diff['missingOnServer'], '_id'));
    }

    public function testDiffServerReturnsEmptySectionsWhenEverythingMatches(): void
    {
        $this->cockpit->collections['servers'] = [
            ['_id' => 'srv-1', 'apiUrl' => 'https://vpn.example.com/x'],
        ];
        $this->cockpit->collections['subscriptions'] = [
            ['_id' => 'sub-1', 'serverId' => 'srv-1', 'outlineKeyId' => 'key-1'],
        ];
        $this->outline->keys = [
            ['id' => 'key-1', 'name' => 'alice', 'accessUrl' => 'ss://1'],
        ];

        $diff = $this->service->diffServer('srv-1');

        $this->assertSame([], $diff['foundOnServer']);
        $this->assertSame([], $diff['missingOnServer']);
    }

    public function testDiffServerRejectsAnUnknownServer(): void
    {
        $this->cockpit->collections['servers'] = [];

        $this->expectException(\InvalidArgumentException::class);
        $this->service->diffServer('missing');
    }

    public function testMigrateResolvesTheDestinationAndDelegatesToSubscriptions(): void
    {
        $this->cockpit->collections['servers'] = [
            ['_id' => 'source', 'apiUrl' => 'https://source/api', 'active' => true],
            ['_id' => 'dest', 'apiUrl' => 'https://dest/api', 'active' => true],
        ];
        $subscriptions = new class extends SubscriptionsService {
            /** @var array{0: string, 1: array<string, mixed>} */
            public array $args = [];
            public function __construct() {}
            public function migrateAllToServer(string $sourceId, array $destinationServer): array
            {
                $this->args = [$sourceId, $destinationServer];

                return ['results' => [], 'moved' => 0, 'failed' => 0];
            }
        };
        $service = new SavedServersService($this->cockpit, $this->outline, $subscriptions);

        $service->migrate('source', 'dest');

        $this->assertSame('source', $subscriptions->args[0]);
        $this->assertSame('dest', $subscriptions->args[1]['_id']);
    }

    /**
     * @dataProvider invalidMigrateDestinations
     */
    public function testMigrateRejectsInvalidDestinations(string $destination, string $message): void
    {
        $this->cockpit->collections['servers'] = [
            ['_id' => 'source', 'apiUrl' => 'https://source/api', 'active' => true],
            ['_id' => 'inactive', 'apiUrl' => 'https://inactive/api', 'active' => false],
        ];
        $subscriptions = new class extends SubscriptionsService { public function __construct() {} };
        $service = new SavedServersService($this->cockpit, $this->outline, $subscriptions);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $service->migrate('source', $destination);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function invalidMigrateDestinations(): array
    {
        return [
            'same as source'       => ['source', 'The destination server must differ from the source.'],
            'inactive destination' => ['inactive', 'The destination server is inactive.'],
            'missing destination'  => ['missing', 'The selected server was not found.'],
        ];
    }
}
