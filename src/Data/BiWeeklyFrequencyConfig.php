<?php

namespace Zap\Data;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Zap\Models\Schedule;

/**
 * @property-read list<string> $daysOfWeek
 */
class BiWeeklyFrequencyConfig extends FrequencyConfig
{
    public ?CarbonInterface $startsOn = null;

    public function __construct(
        public array $days = [],
        CarbonInterface|string|null $startsOn = null,
    ) {
        if ($startsOn === null) {
            return;
        }

        if (is_string($startsOn)) {
            $startsOn = Carbon::parse($startsOn);
        }

        $this->startsOn = $startsOn->copy()->startOfWeek(
            config()->integer('zap.calendar.week_start', CarbonInterface::MONDAY)
        );
    }

    public static function fromArray(array $data): self
    {
        if (! array_key_exists('days', $data) || ! is_array($data['days'])) {
            throw new \InvalidArgumentException("Missing 'days' key in BiWeeklyFrequencyConfig data array.");
        }

        return new self(
            days: $data['days'],
            startsOn: $data['startsOn'] ?? null,
        );
    }

    public function setStartFromStartDate(CarbonInterface $startDate): self
    {
        if ($this->startsOn !== null) {
            return $this;
        }

        $this->startsOn = $startDate->copy()->startOfWeek(
            config()->integer('zap.calendar.week_start', CarbonInterface::MONDAY)
        );

        return $this;
    }

    public function shouldCreateInstance(\Carbon\CarbonInterface $date): bool
    {
        return empty($this->days) || in_array(strtolower($date->format('l')), $this->days) &&
            $this->startsOn->diffInWeeks($date) % 2 === 0;
    }

    public function shouldCreateRecurringInstance(Schedule $schedule, \Carbon\CarbonInterface $date): bool
    {
        $allowedDays = ! empty($this->days) ? $this->days : ['monday'];
        $allowedDayNumbers = array_map(function ($day) {
            return match (strtolower($day)) {
                'sunday' => 0,
                'monday' => 1,
                'tuesday' => 2,
                'wednesday' => 3,
                'thursday' => 4,
                'friday' => 5,
                'saturday' => 6,
                default => 1, // Default to Monday
            };
        }, $allowedDays);

        return in_array($date->dayOfWeek, $allowedDayNumbers) &&
            $this->startsOn->diffInWeeks($date) % 2 === 0;
    }

    public function getNextRecurrence(\Carbon\CarbonInterface $current): \Carbon\CarbonInterface
    {
        return $this->getNextBiWeeklyOccurrence($current, $this->days);
    }

    protected function getNextBiWeeklyOccurrence(\Carbon\CarbonInterface $current, array $allowedDays): \Carbon\CarbonInterface
    {
        $next = $current->copy()->addDay();
        $weekStart = config()->integer('zap.calendar.week_start', CarbonInterface::MONDAY);

        if ($this->startsOn === null) {
            $this->startsOn = $current->copy()->startOfWeek($weekStart);
        }

        if (empty($allowedDays)) {
            $allowedDays = ['monday'];
        }

        // Convert day names to numbers (0 = Sunday, 1 = Monday, etc.)
        $allowedDayNumbers = array_map(function ($day) {
            return match (strtolower($day)) {
                'sunday' => 0,
                'monday' => 1,
                'tuesday' => 2,
                'wednesday' => 3,
                'thursday' => 4,
                'friday' => 5,
                'saturday' => 6,
                default => 1, // Default to Monday
            };
        }, $allowedDays);

        // Find the next allowed day
        while (! in_array($next->dayOfWeek, $allowedDayNumbers) || $this->startsOn->diffInWeeks($next) % 2 !== 0) {
            $next = $next->addDay();

            // Prevent infinite loop
            if ($next->diffInDays($current) > 28) {
                break;
            }
        }

        return $next;
    }
}
