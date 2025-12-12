<?php

namespace Zap\Data\WeeklyEvenOddFrequencyConfig;

use Zap\Helper\DateHelper;

/**
 * @property-read list<string> $daysOfWeek
 */
class WeeklyOddFrequencyConfig extends AbstractWeeklyOddEvenFrequencyConfig
{
    public static function fromArray(array $data): self
    {
        if (! array_key_exists('days', $data)) {
            throw new \InvalidArgumentException("Missing 'days' key in WeeklyOddFrequencyConfig data array.");
        }

        return new self(
            days: $data['days'] ?? []
        );
    }

    protected function isWeekTypeMatch(\Carbon\CarbonInterface $date): bool
    {
        return DateHelper::isDateInOddIsoWeek($date);
    }
}
