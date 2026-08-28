<?php

declare(strict_types=1);

use App\Libraries\AdminAccessService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Throttle\Throttler;
use Config\AdminAccess;

/**
 * @internal
 */
final class AdminAccessServiceTest extends CIUnitTestCase
{
    public function testItAuthenticatesAndClearsTheRateLimitBucket(): void
    {
        $throttler = new FakeAdminThrottler();
        $service = new AdminAccessService($this->config('correct-password'), $throttler);

        $result = $service->authenticate('correct-password', '203.0.113.12');

        $this->assertSame(AdminAccessService::AUTHENTICATED, $result);
        $this->assertSame(1, $throttler->checks);
        $this->assertCount(1, $throttler->removed);
    }

    public function testItRejectsWrongPasswordsAndMissingConfiguration(): void
    {
        $throttler = new FakeAdminThrottler();

        $this->assertSame(
            AdminAccessService::INVALID,
            (new AdminAccessService($this->config('correct-password'), $throttler))->authenticate('wrong-password', '203.0.113.12'),
        );
        $this->assertSame(
            AdminAccessService::INVALID,
            (new AdminAccessService($this->config(''), $throttler))->authenticate('anything', '203.0.113.12'),
        );
    }

    public function testItStopsAttemptingWhenTheIpIsThrottled(): void
    {
        $throttler = new FakeAdminThrottler(false);
        $service = new AdminAccessService($this->config('correct-password'), $throttler);

        $this->assertSame(AdminAccessService::THROTTLED, $service->authenticate('correct-password', '203.0.113.12'));
        $this->assertSame([], $throttler->removed);
    }

    private function config(string $password): AdminAccess
    {
        $config = new AdminAccess();
        $config->password = $password;

        return $config;
    }
}

/** @internal */
final class FakeAdminThrottler extends Throttler
{
    public int $checks = 0;

    /** @var list<string> */
    public array $removed = [];

    public function __construct(private readonly bool $allowed = true)
    {
    }

    public function check(string $key, int $capacity, int $seconds, int $cost = 1): bool
    {
        $this->checks++;

        return $this->allowed;
    }

    public function getTokenTime(): int
    {
        return 0;
    }

    public function remove(string $key): self
    {
        $this->removed[] = $key;

        return $this;
    }
}
