<?php

use App\Libraries\OutlineRequestException;
use App\Libraries\OutlineService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * @internal
 */
final class ClassicControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function tearDown(): void
    {
        parent::tearDown();
        Services::reset();
    }

    public function testIndexReturnsOk(): void
    {
        $result = $this->get('/classic');

        $result->assertStatus(200);
    }

    public function testListKeysReturnsMergedKeysFromOutlineService(): void
    {
        $fake = new class extends OutlineService {
            public function __construct()
            {
            }

            public function listKeys(string $apiUrl): array
            {
                return [['id' => '1', 'name' => 'alice', 'accessUrl' => 'ss://one', 'bytesUsed' => 0, 'usage' => '0 B']];
            }
        };
        Services::injectMock('outline', $fake);

        $result = $this->withBodyFormat('json')->post('/classic/keys/list', ['apiUrl' => 'https://203.0.113.10/api']);

        $result->assertStatus(200);
        $this->assertSame('alice', json_decode($result->getJSON(), true)[0]['name']);
    }

    public function testListKeysRejectsMissingApiUrl(): void
    {
        $result = $this->withBodyFormat('json')->post('/classic/keys/list', []);

        $result->assertStatus(422);
    }

    public function testListKeysReturns502OnOutlineFailure(): void
    {
        $fake = new class extends OutlineService {
            public function __construct()
            {
            }

            public function listKeys(string $apiUrl): array
            {
                throw new OutlineRequestException('Outline server unreachable.');
            }
        };
        Services::injectMock('outline', $fake);

        $result = $this->withBodyFormat('json')->post('/classic/keys/list', ['apiUrl' => 'https://outline.example/api']);

        $result->assertStatus(502);
        $result->assertJSONFragment(['error' => 'Outline server unreachable.']);
    }

    public function testCreateKeyReturnsCreatedKeyWithRequestedName(): void
    {
        $fake = new class extends OutlineService {
            public function __construct()
            {
            }

            public function createKey(string $apiUrl, string $name): array
            {
                return ['id' => '9', 'name' => $name, 'accessUrl' => 'ss://nine', 'bytesUsed' => 0, 'usage' => '0 B'];
            }
        };
        Services::injectMock('outline', $fake);

        $result = $this->withBodyFormat('json')->post('/classic/keys/create', [
            'apiUrl' => 'https://203.0.113.10/api',
            'name' => 'my-new-key',
        ]);

        $result->assertStatus(200);
        $this->assertSame('my-new-key', json_decode($result->getJSON(), true)['name']);
    }

    public function testCreateKeyRejectsMissingName(): void
    {
        $result = $this->withBodyFormat('json')->post('/classic/keys/create', ['apiUrl' => 'https://203.0.113.10/api']);

        $result->assertStatus(422);
    }
}
