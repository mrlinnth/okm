<?php

declare(strict_types=1);

use App\Libraries\SavedServersService;
use App\Libraries\SubscriptionsService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\StreamFilterTrait;
use CodeIgniter\Test\TestLogger;
use Config\Services;

/**
 * @internal
 */
final class SyncServersTest extends CIUnitTestCase
{
    use StreamFilterTrait;

    protected function setUp(): void
    {
        parent::setUp();

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
     * @param array<int, array<string, mixed>>                             $servers
     * @param array<string, array{foundOnServer: array<int, mixed>, missingOnServer: array<int, mixed>}> $diffs
     * @param list<string>                                                 $throwFor
     */
    private function fakeSavedServers(array $servers, array $diffs, array $throwFor = []): SavedServersService
    {
        return new class ($servers, $diffs, $throwFor) extends SavedServersService {
            /** @var list<string> */
            public array $diffed = [];

            public function __construct(private array $servers, private array $diffs, private array $throwFor) {}

            public function list(): array
            {
                return $this->servers;
            }

            public function diffServer(string $serverId): array
            {
                $this->diffed[] = $serverId;

                if (in_array($serverId, $this->throwFor, true)) {
                    throw new \App\Libraries\OutlineRequestException('Outline request failed: unreachable');
                }

                return $this->diffs[$serverId] ?? ['foundOnServer' => [], 'missingOnServer' => []];
            }
        };
    }

    private function fakeSubscriptions(): SubscriptionsService
    {
        return new class extends SubscriptionsService {
            /** @var list<array{0: string, 1: string, 2: string}> */
            public array $created = [];

            /** @var list<string> */
            public array $removed = [];

            public function __construct() {}

            public function createFromOutlineKey(string $serverId, array $outlineKey, \DateTimeImmutable $expiryDate): array
            {
                $this->created[] = [$serverId, (string) $outlineKey['name'], $expiryDate->format('Y-m-d')];

                return ['_id' => 'sub-new'];
            }

            public function removeRecord(string $id): bool
            {
                $this->removed[] = $id;

                return true;
            }
        };
    }

    public function testReconcilesActiveServersAndSkipsInactiveOnes(): void
    {
        $servers = $this->fakeSavedServers(
            [
                ['_id' => 'srv-active', 'active' => true],
                ['_id' => 'srv-off', 'active' => false],
            ],
            [
                'srv-active' => [
                    'foundOnServer'   => [['id' => 'k1', 'name' => 'orphan', 'accessUrl' => 'ss://1']],
                    'missingOnServer' => [['_id' => 'sub-stale']],
                ],
            ],
        );
        $subscriptions = $this->fakeSubscriptions();
        Services::injectMock('savedServers', $servers);
        Services::injectMock('subscriptions', $subscriptions);

        command('servers:sync');

        $this->assertSame(['srv-active'], $servers->diffed);
        $expectedExpiry = SubscriptionsService::addMonthsClamped(new \DateTimeImmutable('today'), 1)->format('Y-m-d');
        $this->assertSame([['srv-active', 'orphan', $expectedExpiry]], $subscriptions->created);
        $this->assertSame(['sub-stale'], $subscriptions->removed);
        $this->assertStringContainsString('Imported: 1, Removed: 1, Failed: 0', $this->getStreamFilterBuffer());
    }

    public function testContinuesPastAServerThatCannotBeDiffed(): void
    {
        $servers = $this->fakeSavedServers(
            [
                ['_id' => 'srv-bad', 'active' => true],
                ['_id' => 'srv-ok', 'active' => true],
            ],
            [
                'srv-ok' => [
                    'foundOnServer'   => [['id' => 'k1', 'name' => 'orphan', 'accessUrl' => 'ss://1']],
                    'missingOnServer' => [],
                ],
            ],
            ['srv-bad'],
        );
        $subscriptions = $this->fakeSubscriptions();
        Services::injectMock('savedServers', $servers);
        Services::injectMock('subscriptions', $subscriptions);

        command('servers:sync');

        $this->assertSame([['srv-ok', 'orphan', SubscriptionsService::addMonthsClamped(new \DateTimeImmutable('today'), 1)->format('Y-m-d')]], $subscriptions->created);
        $this->assertStringContainsString('Imported: 1, Removed: 0, Failed: 1', $this->getStreamFilterBuffer());
        $this->assertLogged('error', 'servers:sync could not diff srv-bad: Outline request failed: unreachable');
    }
}
