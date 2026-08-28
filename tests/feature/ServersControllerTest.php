<?php

use App\Libraries\SavedServersService;
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

    public function __construct()
    {
    }

    public function list(): array
    {
        return $this->rows;
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
final class ServersControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private FakeSavedServers $servers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servers = new FakeSavedServers();
        Services::injectMock('savedServers', $this->servers);
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
        // The full credential blob must never reach the page.
        $result->assertDontSee('serverJson');
        $result->assertDontSee('TOPSECRETCERT');
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

    // --- Task 2.4: delete ------------------------------------------

    public function testDeleteCallsServiceWithIdAndReportsSuccess(): void
    {
        $result = $this->post('/servers/srv-9/delete');

        $result->assertStatus(200);
        $this->assertSame(['srv-9'], $this->servers->deleteArgs);
        $this->assertTrue(json_decode($result->getJSON(), true)['success']);
    }
}
