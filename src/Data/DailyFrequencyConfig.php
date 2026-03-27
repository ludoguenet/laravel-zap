<?php

namespace Zap\Data;

use Carbon\CarbonInterface;
use Zap\Models\Schedule;

class DailyFrequencyConfig extends FrequencyConfig
{
    public static function fromArray(array $data): FrequencyConfig
    {
        return new self;
    }

    public function getNextRecurrence(CarbonInterface $current): CarbonInterface
    {
        return $current->copy()->addDay();
    }

    public function shouldCreateInstance(CarbonInterface $date): bool
    {
        return true;
    }

    public function shouldCreateRecurringInstance(Schedule $schedule, CarbonInterface $date): bool
    {
        return true;
    }
}
