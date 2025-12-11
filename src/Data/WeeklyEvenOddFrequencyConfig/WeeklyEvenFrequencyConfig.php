<?php

namespace Zap\Data\WeeklyEvenOddFrequencyConfig;

use Zap\Data\FrequencyConfig;
use Zap\Helper\DateHelper;

/**
 * @property-read list<string> $daysOfWeek
 */
class WeeklyEvenFrequencyConfig extends AbstractWeeklyOddEvenFrequencyConfig
{

    public static function fromArray(array $data): self
    {
        if (! array_key_exists('days', $data)) {
            throw new \InvalidArgumentException("Missing 'days' key in WeeklyEvenFrequencyConfig data array.");
        }

        return new self(
            days: $data['days'] ?? []
        );
    }

    protected function isWeekTypeMatch(\Carbon\CarbonInterface $date): bool
    {
        return DateHelper::isDateInEvenIsoWeek($date);
    }

}
