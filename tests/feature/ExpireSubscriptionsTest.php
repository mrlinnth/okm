<?php

declare(strict_types=1);

use App\Libraries\SubscriptionsService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\StreamFilterTrait;
use CodeIgniter\Test\TestLogger;
use Config\Services;

/**
 * @internal
 */
final class ExpireSubscriptionsTest extends CIUnitTestCase
{
    use StreamFilterTrait;

    protected function setUp(): void
    {
        parent::setUp();

        // TestLogger accumulates in a static buffer across tests.
        $property = (new ReflectionClass(TestLogger::class))->getProperty('op_logs');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    protected function tearDown(): void
    {
        Services::reset();
        parent::tearDown();
    }

    /**
     * @param list<array<string, mixed>> $expirable
     * @param array<string, string>      $outcomes  subscription id => outcome
     */
    private function fakeService(array $expirable, array $outcomes): SubscriptionsService
    {
        return new class ($expirable, $outcomes) extends SubscriptionsService {
            /** @var list<string> */
            public array $processed = [];

            /**
             * @param list<array<string, mixed>> $expirable
             * @param array<string, string>      $outcomes
             */
            public function __construct(private array $expirable, private array $outcomes) {}

            public function findExpirable(): array
            {
                return $this->expirable;
            }

            public function processExpiry(array $subscription): array
            {
                $id = (string) $subscription['_id'];
                $this->processed[] = $id;
                $outcome = $this->outcomes[$id] ?? 'expired';

                return $outcome === 'failed'
                    ? ['id' => $id, 'outcome' => 'failed', 'error' => 'Outline request failed: boom']
                    : ['id' => $id, 'outcome' => 'expired'];
            }
        };
    }

    public function testExpiresOnlyTheRecordsFindExpirableReturns(): void
    {
        $service = $this->fakeService(
            [['_id' => 'sub-overdue', 'keyName' => 'k']],
            ['sub-overdue' => 'expired'],
        );
        Services::injectMock('subscriptions', $service);

        command('subscriptions:expire');

        $this->assertSame(['sub-overdue'], $service->processed);
        $this->assertStringContainsString('Expired: 1, Failed: 0', $this->getStreamFilterBuffer());
        $this->assertFalse(TestLogger::didLog('error', '', false), 'nothing should be logged on a clean run');
    }

    public function testContinuesPastAFailureAndLogsIt(): void
    {
        $service = $this->fakeService(
            [
                ['_id' => 'sub-a', 'keyName' => 'a'],
                ['_id' => 'sub-b', 'keyName' => 'b'],
                ['_id' => 'sub-c', 'keyName' => 'c'],
            ],
            ['sub-b' => 'failed'],
        );
        Services::injectMock('subscriptions', $service);

        command('subscriptions:expire');

        $this->assertSame(['sub-a', 'sub-b', 'sub-c'], $service->processed);
        $this->assertStringContainsString('Expired: 2, Failed: 1', $this->getStreamFilterBuffer());
        $this->assertLogged('error', 'subscriptions:expire could not expire sub-b: Outline request failed: boom');
    }

    public function testReportsNothingToDoOnAnEmptyLedger(): void
    {
        Services::injectMock('subscriptions', $this->fakeService([], []));

        command('subscriptions:expire');

        $this->assertStringContainsString('Expired: 0, Failed: 0', $this->getStreamFilterBuffer());
    }
}
