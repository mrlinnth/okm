<?php

use App\Libraries\SavedServersService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * @internal
 */
final class ServersControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function tearDown(): void
    {
        parent::tearDown();
        Services::reset();
    }

    /**
     * @param array<int, array<string, mixed>> $list
     */
    private function mockSavedServers(array $list = []): void
    {
        $fake = new class ($list) extends SavedServersService {
            /** @param array<int, array<string, mixed>> $rows */
            public function __construct(private array $rows)
            {
            }

            public function list(): array
            {
                return $this->rows;
            }
        };

        Services::injectMock('savedServers', $fake);
    }

    public function testIndexReturnsOkAndRendersTheServerList(): void
    {
        $this->mockSavedServers([
            ['_id' => '1', 'label' => 'HK-1', 'apiUrl' => 'https://a.example.com/x', 'active' => true],
        ]);

        $result = $this->get('/servers');

        $result->assertStatus(200);
        $result->assertSee('HK-1');
    }

    public function testStoreStubReturnsJson(): void
    {
        $result = $this->withBodyFormat('json')->post('/servers', []);

        $result->assertStatus(200);
        $this->assertSame('[]', $result->getJSON());
    }

    public function testActivateStubReturnsJson(): void
    {
        $result = $this->post('/servers/abc/activate');

        $result->assertStatus(200);
        $this->assertSame('[]', $result->getJSON());
    }

    public function testDeactivateStubReturnsJson(): void
    {
        $result = $this->post('/servers/abc/deactivate');

        $result->assertStatus(200);
        $this->assertSame('[]', $result->getJSON());
    }

    public function testDeleteStubReturnsJson(): void
    {
        $result = $this->post('/servers/abc/delete');

        $result->assertStatus(200);
        $this->assertSame('[]', $result->getJSON());
    }
}
