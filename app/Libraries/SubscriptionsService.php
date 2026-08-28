<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Coordinates subscription lifecycle operations.
 */
class SubscriptionsService
{
    /**
     * Add calendar months without allowing dates such as January 31 to
     * overflow into March when the target month has fewer days.
     */
    public static function addMonthsClamped(\DateTimeImmutable $from, int $months): \DateTimeImmutable
    {
        $targetMonth = $from
            ->modify('first day of this month')
            ->modify(sprintf('%+d months', $months));

        $year = (int) $targetMonth->format('Y');
        $month = (int) $targetMonth->format('n');
        $lastDay = (int) $targetMonth->modify('last day of this month')->format('j');
        $day = min((int) $from->format('j'), $lastDay);

        return $targetMonth->setDate($year, $month, $day);
    }
}
