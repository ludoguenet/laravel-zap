<?php

namespace Zap\Helper;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class DateHelper
{
    /**
     * Checks if a given date falls in an even ISO week.
     *
     * @param  \Carbon\CarbonInterface|string  $date  The date to check
     * @return bool True if the ISO week is even, false otherwise
     */
    public static function isDateInEvenIsoWeek(\Carbon\CarbonInterface|string $date): bool
    {
        $checkDate = Carbon::parse($date);

        return $checkDate->isoWeek() % 2 === 0;
    }

    /**
     * Checks if a given date falls in an odd ISO week.
     *
     * @param  \Carbon\CarbonInterface|string  $date  The date to check
     * @return bool True if the ISO week is odd, false otherwise
     */
    public static function isDateInOddIsoWeek(\Carbon\CarbonInterface|string $date): bool
    {
        return ! self::isDateInEvenIsoWeek($date);
    }

    /**
     * Converts day names to their corresponding numbers (0 = Sunday, 1 = Monday, etc.).
     *
     * @param  string[]  $days  Array of day names
     * @return int[] Array of day numbers
     */
    public static function getDayNumbers(array $days): array
    {
        return array_map(fn ($day) => match (strtolower($day)) {
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            default => 1, // Default Monday
        }, $days);
    }

    /**
     * Calculate the next date that falls in a week with the desired parity (odd or even),
     * starting from the given date.
     *
     * @param  \Carbon\CarbonInterface  $current  The starting date for the calculation
     * @param  bool  $isOddWeekDesired  True to get the next odd week, false to get the next even week
     * @return \Carbon\CarbonInterface The next date that falls in a week matching the desired parity
     */
    public static function nextWeekByParity(CarbonInterface $current, bool $isOddWeekDesired): CarbonInterface
    {
        // Check if the current week is odd
        $isCurrentOdd = \Zap\Helper\DateHelper::isDateInOddIsoWeek($current);

        // If the current week already matches the desired parity, jump 2 weeks
        // Otherwise, jump 1 week to reach the desired parity
        return $current->copy()->addWeeks($isCurrentOdd === $isOddWeekDesired ? 2 : 1);
    }

    /**
     * Get the next date that falls in an odd ISO week, starting from the given date.
     *
     * @param  \Carbon\CarbonInterface  $current  The current date
     * @return \Carbon\CarbonInterface The next date that falls on an odd week
     */
    public static function nextWeekOdd(CarbonInterface $current): CarbonInterface
    {
        return self::nextWeekByParity($current, true);
    }

    /**
     * Get the next date that falls in an even ISO week, starting from the given date.
     *
     * @param  \Carbon\CarbonInterface  $current  The current date
     * @return \Carbon\CarbonInterface The next date that falls on an even week
     */
    public static function nextWeekEven(CarbonInterface $current): CarbonInterface
    {
        return self::nextWeekByParity($current, false);
    }
}
