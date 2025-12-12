<?php

namespace Zap\Data;

use Zap\Models\Schedule;

/**
 * @property-read list<int>|null $daysOfMonth
 */
class MonthlyFrequencyConfig extends FrequencyConfig
{
    public function __construct(
        public ?array $days_of_month
    ) {}

    public static function fromArray(array $data): self
    {
        if (array_key_exists('day_of_month', $data) && ! array_key_exists('days_of_month', $data)) {
            $data['days_of_month'] = [$data['day_of_month']];
            unset($data['day_of_month']);
        }

        return new self(
            days_of_month: $data['days_of_month'] ?? null,
        );
    }

    public function getNextRecurrence(\Carbon\CarbonInterface $current): \Carbon\CarbonInterface
    {
        $daysOfMonth = $this->days_of_month ?? [$current->day];
        if ($current->day >= max($daysOfMonth)) {
            $dayOfMonth = min($daysOfMonth);

            return $current->copy()->addMonth()->day($dayOfMonth);
        }
        $dayOfMonth = min(array_filter($daysOfMonth, fn ($day) => $day > $current->day));

        return $current->copy()->day($dayOfMonth);
    }

    public function shouldCreateInstance(\Carbon\CarbonInterface $date): bool
    {
        $daysOfMonth = $this->days_of_month ?? [$date->day];

        return in_array($date->day, $daysOfMonth);
    }

    public function shouldCreateRecurringInstance(Schedule $schedule, \Carbon\CarbonInterface $date): bool
    {
        $daysOfMonth = $this->days_of_month ?? [$schedule->start_date->day];

        return in_array($date->day, $daysOfMonth);
    }
}
