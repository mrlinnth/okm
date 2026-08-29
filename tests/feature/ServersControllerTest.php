<?php

use App\Libraries\SavedServersService;
use App\Libraries\SubscriptionsService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * In-memory stand-in for SavedServersService. Skips the real constructor
 * (no Cockpit/Outline wiring) and records every call so tests can assert
 * delegation. Cache-invalidation itself is covered by CockpitServiceTest.
 *
 * @internal
 */
final class FakeSavedServers extends SavedServersService
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    /** @var array<int, array{0: string, 1: string, 2: ?string}> */
    public array $createArgs = [];

    /** @var array<int, array{0: string, 1: bool}> */
    public array $setActiveArgs = [];

    /** @var array<int, string> */
    public array $deleteArgs = [];

    public ?\Throwable $createThrows = null;

    /** @var array{foundOnServer: array<int, mixed>, missingOnServer: array<int, mixed>} */
    public array $diff = ['foundOnServer' => [], 'missingOnServer' => []];

    public ?\Throwable $diffThrows = null;

    /** @var array<int, string> */
    public array $reconcileArgs = [];

    /** @var array{imported: list<string>, removed: list<string>, failed: int} */
    public array $reconcileResult = ['imported' => [], 'removed' => [], 'failed' => 0];

    public ?\Throwable $reconcileThrows = null;

    /** @var array<int, array{0: string, 1: string}> */
    public array $migrateArgs = [];

    /** @var array{results: array<int, mixed>, moved: int, failed: int} */
    public array $migrateResult = ['results' => [], 'moved' => 0, 'failed' => 0];

    public ?\Throwable $migrateThrows = null;

    public function __construct()
    {
    }

    public function list(): array
    {
        return $this->rows;
    }

    public function diffServer(string $serverId): array
    {
        if ($this->diffThrows !== null) {
            throw $this->diffThrows;
        }

        return $this->diff;
    }

    public function reconcileServer(string $serverId): array
    {
        $this->reconcileArgs[] = $serverId;

        if ($this->reconcileThrows !== null) {
            throw $this->reconcileThrows;
        }

        return $this->reconcileResult;
    }

    public function migrate(string $sourceId, string $destinationId): array
    {
        $this->migrateArgs[] = [$sourceId, $destinationId];

        if ($this->migrateThrows !== null) {
            throw $this->migrateThrows;
        }

        return $this->migrateResult;
    }

    public function create(string $label, string $serverJson, ?string $publicHost): array
    {
        $this->createArgs[] = [$label, $serverJson, $publicHost];

        if ($this->createThrows !== null) {
            throw $this->createThrows;
        }

        return [
            '_id'        => 'new-id',
            'label'      => $label,
            'serverJson' => $serverJson,
            'apiUrl'     => 'https://derived.example.com/x',
            'publicHost' => $publicHost ?? '',
            'active'     => true,
        ];
    }

    public function setActive(string $id, bool $active): array
    {
        $this->setActiveArgs[] = [$id, $active];

        return [
            '_id'        => $id,
            'label'      => 'HK-1',
            'apiUrl'     => 'https://derived.example.com/x',
            'publicHost' => '',
            'active'     => $active,
        ];
    }

    public function delete(string $id): bool
    {
        $this->deleteArgs[] = $id;

        return true;
    }
}

/**
 * @internal
 */
final class FakeSubscriptionsForServers extends SubscriptionsService
{
    public int $count = 0;

    /** @var array<int, array{0: string, 1: string, 2: string}> */
    public array $importArgs = [];

    /** @var array{imported: int, failed: int, failures: array<int, array{name: string, error: string}>} */
    public array $importSummary = ['imported' => 0, 'failed' => 0, 'failures' => []];

    public function __construct()
    {
    }

    public function countByServer(string $serverId): int
    {
        return $this->count;
    }

    public function importAllFromServer(string $serverId, string $apiUrl, \DateTimeImmutable $expiryDate): array
    {
        $this->importArgs[] = [$serverId, $apiUrl, $expiryDate->format('Y-m-d')];

        return $this->importSummary;
    }

}

/**
 * @internal
 */
final class ServersControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const CSRF_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private FakeSavedServers $servers;
    private FakeSubscriptionsForServers $subscriptions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servers = new FakeSavedServers();
        $this->subscriptions = new FakeSubscriptionsForServers();
        Services::injectMock('savedServers', $this->servers);
        Services::injectMock('subscriptions', $this->subscriptions);
        $this->withSession(['adminAuthenticated' => true, 'csrf_test_name' => self::CSRF_TOKEN]);
        $this->withHeaders(['X-CSRF-TOKEN' => self::CSRF_TOKEN]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Services::reset();
    }

    // --- Task 2.1: list endpoint ---------------------------------------

    public function testIndexReturnsOkAndRendersTrimmedServerRecords(): void
    {
        $this->servers->rows = [[
            '_id'        => 'srv-1',
            'label'      => 'HK-1',
            'serverJson' => '{"apiUrl":"https://vpn.example.com:8443/x","certSha256":"TOPSECRETCERT"}',
            'apiUrl'     => 'https://vpn.example.com:8443/x',
            'publicHost' => 'vpn.example.com',
            'active'     => true,
        ]];

        $result = $this->get('/servers');

        $result->assertStatus(200);
        $result->assertSee('HK-1');
        $result->assertSee('vpn.example.com');
        // The stored credential blob must never reach the page — check the
        // cert value and the raw serverJson string, both unique to the blob.
        $result->assertDontSee('TOPSECRETCERT');
        $result->assertDontSee('"certSha256":"TOPSECRETCERT"');
    }

    // --- Task 2.2: add server endpoint -------------------------------

    public function testStoreCreatesServerFromValidInput(): void
    {
        $json = '{"apiUrl":"https://vpn.example.com/x"}';

        $result = $this->withBodyFormat('json')->post('/servers', [
            'label'      => 'HK-1',
            'serverJson' => $json,
            'publicHost' => 'vpn.example.com',
        ]);

        $result->assertStatus(200);
        $this->assertSame(['HK-1', $json, 'vpn.example.com'], $this->servers->createArgs[0]);

        $decoded = json_decode($result->getJSON(), true);
        $this->assertSame('new-id', $decoded['id']);
        $this->assertSame('HK-1', $decoded['label']);
        $this->assertArrayNotHasKey('serverJson', $decoded);
    }

    public function testStoreImportsExistingKeysAndReturnsTheSummary(): void
    {
        $this->subscriptions->importSummary = [
            'imported' => 3,
            'failed'   => 1,
            'failures' => [['name' => 'bad-key', 'error' => 'Cockpit write failed.']],
        ];

        $result = $this->withBodyFormat('json')->post('/servers', [
            'label'      => 'HK-1',
            'serverJson' => '{"apiUrl":"https://vpn.example.com/x"}',
        ]);

        $expectedExpiry = SubscriptionsService::addMonthsClamped(new \DateTimeImmutable('today'), 1)->format('Y-m-d');

        $result->assertStatus(200);
        $this->assertSame(['new-id', 'https://derived.example.com/x', $expectedExpiry], $this->subscriptions->importArgs[0]);

        $decoded = json_decode($result->getJSON(), true);
        $this->assertSame(3, $decoded['import']['imported']);
        $this->assertSame(1, $decoded['import']['failed']);
        $this->assertSame('bad-key', $decoded['import']['failures'][0]['name']);
    }

    public function testStorePassesNullPublicHostWhenOmitted(): void
    {
        $this->withBodyFormat('json')->post('/servers', [
            'label'      => 'HK-1',
            'serverJson' => '{"apiUrl":"https://vpn.example.com/x"}',
        ]);

        $this->assertNull($this->servers->createArgs[0][2]);
    }

    public function testStoreRejectsMissingLabelWithoutCallingCreate(): void
    {
        $result = $this->withBodyFormat('json')->post('/servers', ['serverJson' => '{}']);

        $result->assertStatus(422);
        $this->assertSame([], $this->servers->createArgs);
    }

    public function testStoreRejectsMissingServerJsonWithoutCallingCreate(): void
    {
        $result = $this->withBodyFormat('json')->post('/servers', ['label' => 'HK-1']);

        $result->assertStatus(422);
        $this->assertSame([], $this->servers->createArgs);
    }

    public function testStoreReturns422WithInvalidJsonMessage(): void
    {
        $this->servers->createThrows = new \App\Libraries\InvalidServerJsonException('Server JSON could not be parsed.');

        $result = $this->withBodyFormat('json')->post('/servers', [
            'label'      => 'HK-1',
            'serverJson' => 'not json',
        ]);

        $result->assertStatus(422);
        $result->assertJSONFragment(['error' => 'Server JSON could not be parsed.']);
    }

    public function testStoreReturns422WithDistinctUnreachableMessage(): void
    {
        $this->servers->createThrows = new \App\Libraries\ServerUnreachableException('Could not reach the Outline server at https://vpn.example.com/x.');

        $result = $this->withBodyFormat('json')->post('/servers', [
            'label'      => 'HK-1',
            'serverJson' => '{"apiUrl":"https://vpn.example.com/x"}',
        ]);

        $result->assertStatus(422);
        $result->assertJSONFragment(['error' => 'Could not reach the Outline server at https://vpn.example.com/x.']);
    }

    // --- Task 2.3: activate / deactivate ----------------------------

    public function testActivatePassesTrueToSetActiveAndReturnsUpdatedRecord(): void
    {
        $result = $this->post('/servers/srv-9/activate');

        $result->assertStatus(200);
        $this->assertSame(['srv-9', true], $this->servers->setActiveArgs[0]);

        $decoded = json_decode($result->getJSON(), true);
        $this->assertSame('srv-9', $decoded['id']);
        $this->assertTrue($decoded['active']);
        $this->assertArrayNotHasKey('serverJson', $decoded);
    }

    public function testDeactivatePassesFalseToSetActive(): void
    {
        $result = $this->post('/servers/srv-9/deactivate');

        $result->assertStatus(200);
        $this->assertSame(['srv-9', false], $this->servers->setActiveArgs[0]);
        $this->assertFalse(json_decode($result->getJSON(), true)['active']);
    }

    // --- Phase 3: sync now ----------------------------------------

    public function testSyncReturnsBothDiffSections(): void
    {
        $this->servers->diff = [
            'foundOnServer'   => [['id' => 'k-x', 'name' => 'manual', 'accessUrl' => 'ss://x']],
            'missingOnServer' => [['_id' => 'sub-stale', 'outlineKeyId' => 'gone']],
        ];

        $result = $this->post('/servers/srv-1/sync');

        $result->assertStatus(200);
        $decoded = json_decode($result->getJSON(), true);
        $this->assertSame('manual', $decoded['foundOnServer'][0]['name']);
        $this->assertSame('sub-stale', $decoded['missingOnServer'][0]['_id']);
    }

    public function testSyncReturns502WhenTheServerCannotBeReached(): void
    {
        $this->servers->diffThrows = new \App\Libraries\OutlineRequestException('Outline request failed: timeout');

        $result = $this->post('/servers/srv-1/sync');

        $result->assertStatus(502);
    }

    public function testReconcileDelegatesToReconcileServerAndReturnsTheSummary(): void
    {
        $this->servers->reconcileResult = [
            'imported' => ['manual-key'],
            'removed'  => ['Bob'],
            'failed'   => 0,
        ];

        $result = $this->post('/servers/srv-1/reconcile');

        $result->assertStatus(200);
        $this->assertSame(['srv-1'], $this->servers->reconcileArgs);
        $decoded = json_decode($result->getJSON(), true);
        $this->assertSame(['manual-key'], $decoded['imported']);
        $this->assertSame(['Bob'], $decoded['removed']);
        $this->assertSame(0, $decoded['failed']);
    }

    public function testReconcileReturns502WhenTheServerCannotBeReached(): void
    {
        $this->servers->reconcileThrows = new \App\Libraries\OutlineRequestException('Outline request failed: timeout');

        $result = $this->post('/servers/srv-1/reconcile');

        $result->assertStatus(502);
    }

    // --- Phase 4: migrate ----------------------------------------

    public function testMigrateDelegatesAndReturnsPerSubscriptionResults(): void
    {
        $this->servers->migrateResult = [
            'results' => [
                ['id' => 'sub-1', 'recipientName' => 'Alice', 'status' => 'success', 'renamed_from' => 'alice'],
                ['id' => 'sub-2', 'recipientName' => 'Bob', 'status' => 'failed', 'error' => 'boom'],
            ],
            'moved'  => 1,
            'failed' => 1,
        ];

        $result = $this->withBodyFormat('json')->post('/servers/src/migrate', [
            'destinationServerId' => 'dst',
        ]);

        $result->assertStatus(200);
        $this->assertSame(['src', 'dst'], $this->servers->migrateArgs[0]);
        $decoded = json_decode($result->getJSON(), true);
        $this->assertSame('alice', $decoded['results'][0]['renamed_from']);
        $this->assertSame(1, $decoded['failed']);
    }

    public function testMigrateRejectsAMissingDestination(): void
    {
        $result = $this->withBodyFormat('json')->post('/servers/src/migrate', []);

        $result->assertStatus(422);
        $this->assertSame([], $this->servers->migrateArgs);
    }

    public function testMigrateSurfacesValidationErrorsAs422(): void
    {
        $this->servers->migrateThrows = new \InvalidArgumentException('The destination server is inactive.');

        $result = $this->withBodyFormat('json')->post('/servers/src/migrate', [
            'destinationServerId' => 'dst',
        ]);

        $result->assertStatus(422);
        $result->assertJSONFragment(['error' => 'The destination server is inactive.']);
    }

    // --- Task 2.4: delete ------------------------------------------

    public function testDeleteCallsServiceWithIdAndReportsSuccess(): void
    {
        $result = $this->post('/servers/srv-9/delete');

        $result->assertStatus(200);
        $this->assertSame(['srv-9'], $this->servers->deleteArgs);
        $this->assertTrue(json_decode($result->getJSON(), true)['success']);
    }

    public function testDeleteRejectsServerWithSubscriptionsWithoutCallingDelete(): void
    {
        $this->subscriptions->count = 2;

        $result = $this->post('/servers/srv-9/delete');

        $result->assertStatus(422);
        $result->assertJSONFragment(['error' => 'Cannot delete a server with 2 active subscriptions — deactivate it instead.']);
        $this->assertSame([], $this->servers->deleteArgs);
    }
}
