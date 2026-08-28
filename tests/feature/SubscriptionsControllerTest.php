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

            public function __construct()
            {
            }

            public function list(): array
            {
                $this->listCalls++;

                return [];
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
}
