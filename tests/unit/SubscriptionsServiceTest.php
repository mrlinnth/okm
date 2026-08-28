<?php

declare(strict_types=1);

use App\Libraries\SubscriptionsService;
use App\Libraries\CockpitService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SubscriptionsServiceTest extends CIUnitTestCase
{
    public function testListUsesCockpitCollectionAndSortsByExpiryDate(): void
    {
        $cockpit = new class extends CockpitService {
            /** @var array<int, array<string, mixed>> */
            public array $rows = [
                ['_id' => 'late', 'expiryDate' => '2026-12-01'],
                ['_id' => 'soon', 'expiryDate' => '2026-01-01'],
                ['_id' => 'middle', 'expiryDate' => '2026-06-01'],
            ];

            public function __construct()
            {
            }

            public function getCollectionCached(string $model, array $params = [], ?int $ttl = null): array
            {
                return $this->rows;
            }
        };

        $subscriptions = (new SubscriptionsService($cockpit))->list();

        $this->assertSame(['soon', 'middle', 'late'], array_column($subscriptions, '_id'));
    }

    public function testGenerateTokenIsUrlSafeAndUniqueAcrossLargeSample(): void
    {
        $tokens = [];

        for ($i = 0; $i < 10000; $i++) {
            $token = SubscriptionsService::generateToken();

            $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $token);
            $tokens[$token] = true;
        }

        $this->assertCount(10000, $tokens);
    }

    /**
     * @dataProvider addMonthsClampedCases
     */
    public function testAddMonthsClampedPreservesCalendarDates(string $from, int $months, string $expected): void
    {
        $result = SubscriptionsService::addMonthsClamped(new \DateTimeImmutable($from), $months);

        $this->assertSame($expected, $result->format('Y-m-d'));
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: string}>
     */
    public static function addMonthsClampedCases(): array
    {
        return [
            'mid-month' => ['2025-01-15', 2, '2025-03-15'],
            'non-leap-year February' => ['2025-01-31', 1, '2025-02-28'],
            'leap-year February' => ['2024-01-31', 1, '2024-02-29'],
            'thirty-first to thirty-first' => ['2025-03-31', 2, '2025-05-31'],
        ];
    }
}
