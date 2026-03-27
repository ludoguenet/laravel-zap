<?php

namespace Zap\Data\MonthlyFrequencyConfig;

use Carbon\CarbonInterface;
use Zap\Data\FrequencyConfig;
use Zap\Models\Schedule;

/**
 * @property-read list<int>|null $daysOfMonth
 *
 * @phpstan-consistent-constructor
 */
abstract class AbstractMonthlyFrequencyConfig extends FrequencyConfig
{
    public function __construct(
        public ?array $days_of_month,
        public ?int $start_month = null
    ) {}

    public static function fromArray(array $data): static
    {
        if (array_key_exists('day_of_month', $data) && ! array_key_exists('days_of_month', $data)) {
            $data['days_of_month'] = [$data['day_of_month']];
            unset($data['day_of_month']);
        }

        return new static(
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

    public function getNextRecurrence(CarbonInterface $current): CarbonInterface
    {
        $daysOfMonth = $this->days_of_month ?? [$current->day];
        if ($current->day >= max($daysOfMonth)) {
            $dayOfMonth = min($daysOfMonth);

            return $current->copy()->addMonths($this::getFrequency())->day($dayOfMonth);
        }
        $dayOfMonth = min(array_filter($daysOfMonth, fn ($day) => $day > $current->day));

        return $current->copy()->day($dayOfMonth);
    }

    public function shouldCreateInstance(CarbonInterface $date): bool
    {
        $daysOfMonth = $this->days_of_month ?? [$date->day];
        $monthDiff = ($date->month - $this->start_month + 12) % $this::getFrequency();

        return in_array($date->day, $daysOfMonth) && $monthDiff === 0;
    }

    public function shouldCreateRecurringInstance(Schedule $schedule, CarbonInterface $date): bool
    {
        $daysOfMonth = $this->days_of_month ?? [$schedule->start_date->day];
        $monthDiff = ($date->month - $this->start_month + 12) % $this::getFrequency();

        return in_array($date->day, $daysOfMonth) && $monthDiff === 0;
    }

    /** @return int<1, 12> */
    abstract protected static function getFrequency(): int;
}
