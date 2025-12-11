<?php

namespace Zap\Data\WeeklyEvenOddFrequencyConfig;

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

    abstract protected function isWeekTypeMatch(\Carbon\CarbonInterface $date): bool;


    public function shouldCreateInstance(\Carbon\CarbonInterface $date): bool
    {
        return empty($this->days) || in_array(strtolower($date->format('l')), $this->days);
    }

    public function shouldCreateRecurringInstance(Schedule $schedule, \Carbon\CarbonInterface $date): bool
    {
        $allowedDays = ! empty($this->days) ? $this->days : ['monday'];
        $allowedDayNumbers = DateHelper::getDayNumbers($allowedDays);

        return $this->isWeekTypeMatch($date) && in_array($date->dayOfWeek, $allowedDayNumbers);
    }

    public function getNextRecurrence(\Carbon\CarbonInterface $current): \Carbon\CarbonInterface
    {
        return $this->getNextWeeklyOccurrence($current, $this->days);
    }

    protected function getNextWeeklyOccurrence(\Carbon\CarbonInterface $current, array $allowedDays): \Carbon\CarbonInterface
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
