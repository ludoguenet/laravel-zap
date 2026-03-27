<?php

namespace Zap\Data\WeeklyEvenOddFrequencyConfig;

use Carbon\CarbonInterface;
use Zap\Data\FrequencyConfig;
use Zap\Helper\DateHelper;
use Zap\Models\Schedule;

/**
 * @property-read list<string> $daysOfWeek
 */
abstract class AbstractWeeklyOddEvenFrequencyConfig extends FrequencyConfig
{
    public function __construct(
        public array $days = []
    ) {}

    abstract protected function isWeekTypeMatch(CarbonInterface $date): bool;

    public function shouldCreateInstance(CarbonInterface $date): bool
    {
        return empty($this->days) || in_array(strtolower($date->format('l')), $this->days);
    }

    public function shouldCreateRecurringInstance(Schedule $schedule, CarbonInterface $date): bool
    {
        $allowedDays = ! empty($this->days) ? $this->days : ['monday'];
        $allowedDayNumbers = DateHelper::getDayNumbers($allowedDays);

        return $this->isWeekTypeMatch($date) && in_array($date->dayOfWeek, $allowedDayNumbers);
    }

    public function getNextRecurrence(CarbonInterface $current): CarbonInterface
    {
        return $this->getNextWeeklyOccurrence($current, $this->days);
    }

    protected function getNextWeeklyOccurrence(CarbonInterface $current, array $allowedDays): CarbonInterface
    {
        $next = $current->copy()->addDay();
        $allowedDayNumbers = DateHelper::getDayNumbers($allowedDays);

        while (true) {
            $isAllowedDay = in_array($next->dayOfWeek, $allowedDayNumbers);

            if ($this->isWeekTypeMatch($next) && $isAllowedDay) {
                break;
            }

            $next->addDay();

            if ($next->diffInDays($current) > 14) {
                break;
            }
        }

        return $next;
    }
}
