<?php

namespace Zap\Data;

use Carbon\CarbonInterface;
use Zap\Models\Schedule;

/**
 * @property-read list<int>|null $daysOfMonth
 */
class QuarterlyFrequencyConfig extends FrequencyConfig
{
    public function __construct(
        public ?array $days_of_month = [],
        public ?int $start_month = null,
    ) {}

    public static function fromArray(array $data): self
    {
        if (array_key_exists('day_of_month', $data) && ! array_key_exists('days_of_month', $data)) {
            $data['days_of_month'] = [$data['day_of_month']];
            unset($data['day_of_month']);
        }

        return new self(
            days_of_month: $data['days_of_month'],
            start_month: $data['start_month'] ?? null,
        );
    }

    public function setStartFromStartDate(CarbonInterface $startDate): self
    {
        if ($this->start_month === null) {
            $this->start_month = $startDate->month;
        }

        return $this;
    }

    public function getNextRecurrence(\Carbon\CarbonInterface $current): \Carbon\CarbonInterface
    {
        $daysOfMonth = $this->days_of_month ?? [$current->day];
        if ($current->day >= max($daysOfMonth)) {
            $dayOfMonth = min($daysOfMonth);

            return $current->copy()->addMonths(3)->day($dayOfMonth);
        }
        $dayOfMonth = min(array_filter($daysOfMonth, fn ($day) => $day > $current->day));

        return $current->copy()->day($dayOfMonth);
    }

    public function shouldCreateInstance(\Carbon\CarbonInterface $date): bool
    {
        $daysOfMonth = $this->days_of_month ?? [$date->day];
        $monthDiff = ($date->month - $this->start_month + 12) % 3;

        return in_array($date->day, $daysOfMonth) && $monthDiff === 0;
    }

    public function shouldCreateRecurringInstance(Schedule $schedule, \Carbon\CarbonInterface $date): bool
    {
        $daysOfMonth = $this->days_of_month ?? [$schedule->start_date->day];
        $monthDiff = ($date->month - $this->start_month + 12) % 3;

        return in_array($date->day, $daysOfMonth) && $monthDiff === 0;
    }
}
