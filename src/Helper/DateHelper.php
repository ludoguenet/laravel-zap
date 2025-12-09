<?php

namespace Zap\Helper;

use Carbon\Carbon;

class DateHelper
{
    public static function isDateInEvenIsoWeek(string $date): bool
    {
        $checkDate = $date instanceof Carbon ? $date : Carbon::parse($date);
        return $checkDate->isoWeek() % 2 === 0;
    }

    public static function getDayNumbers(array $days): array
    {
        return array_map(fn($day) => match (strtolower($day)) {
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

}
