<?php

declare(strict_types=1);

use App\Libraries\AdminAccessService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * @internal
 */
final class AdminAccessControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const CSRF_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private AdminAccessService $adminAccess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminAccess = new class extends AdminAccessService {
            public string $result = self::AUTHENTICATED;

            /** @var array{0: string, 1: string}|null */
            public ?array $arguments = null;

            public function __construct()
            {
            }

            public function authenticate(string $password, string $ipAddress): string
            {
                $this->arguments = [$password, $ipAddress];

                return $this->result;
            }
        };

        Services::injectMock('adminAccess', $this->adminAccess);
        $this->withSession(['csrf_test_name' => self::CSRF_TOKEN]);
        $this->withHeaders(['X-CSRF-TOKEN' => self::CSRF_TOKEN]);
    }

    protected function tearDown(): void
    {
        Services::reset();
        parent::tearDown();
    }

    public function testManagePageRendersForUnauthenticatedVisitor(): void
    {
        $result = $this->get('/manage');

        $result->assertStatus(200);
        $result->assertSee('Manage Subscriptions');
        $result->assertDontSee('Saved Servers');
    }

    public function testValidPasswordCreatesSessionAndRedirectsToSubscriptions(): void
    {
        $this->post('/manage', ['password' => 'correct-password'])
            ->assertRedirectTo('/subscriptions');

        $this->assertSame(['correct-password', '0.0.0.0'], $this->adminAccess->arguments);
        $this->assertTrue(session()->get('adminAuthenticated'));
    }

    public function testInvalidPasswordRedirectsBackWithoutAuthenticating(): void
    {
        $this->adminAccess->result = AdminAccessService::INVALID;

        $this->post('/manage', ['password' => 'wrong-password'])
            ->assertRedirectTo('/manage');

        $this->assertNull(session()->get('adminAuthenticated'));
    }

    public function testProtectedPagesRedirectAndProtectedJsonEndpointsReturnUnauthorized(): void
    {
        $this->get('/servers')->assertRedirectTo('/manage');

        $result = $this->post('/servers', []);
        $result->assertStatus(401);
        $result->assertJSONFragment([
            'error' => 'Admin authentication required.',
            'login' => '/manage',
        ]);
    }

    public function testLogoutDestroysTheAdminSession(): void
    {
        $this->withSession([
            'adminAuthenticated' => true,
            'csrf_test_name' => self::CSRF_TOKEN,
        ]);

        $this->post('/manage/logout')->assertRedirectTo('/manage');
        $this->withSession(['csrf_test_name' => self::CSRF_TOKEN]);
        $this->get('/servers')->assertRedirectTo('/manage');
    }
}
