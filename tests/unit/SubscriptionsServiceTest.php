<?php

declare(strict_types=1);

use App\Libraries\SubscriptionsService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SubscriptionsServiceTest extends CIUnitTestCase
{
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
