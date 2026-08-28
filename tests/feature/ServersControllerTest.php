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

    // --- Task 2.2: add server endpoint (stub until implemented) --------

    public function testStoreStub(): void
    {
        $result = $this->withBodyFormat('json')->post('/servers', ['label' => 'x', 'serverJson' => '{}']);

        $result->assertStatus(200);
    }

    // --- Tasks 2.3 / 2.4: stubs until implemented ---------------------

    public function testActivateStub(): void
    {
        $this->post('/servers/abc/activate')->assertStatus(200);
    }

    public function testDeactivateStub(): void
    {
        $this->post('/servers/abc/deactivate')->assertStatus(200);
    }

    public function testDeleteStub(): void
    {
        $this->post('/servers/abc/delete')->assertStatus(200);
    }
}
