<?php

namespace Zap\Data;

use Zap\Models\Schedule;

/**
 * @property-read list<string> $daysOfWeek
 */
class WeeklyFrequencyConfig extends FrequencyConfig
{
    public function __construct(
        public array $days = []
    ) {}

    public static function fromArray(array $data): self
    {
        if (! array_key_exists('days', $data)) {
            throw new \InvalidArgumentException("Missing 'days' key in WeeklyFrequencyConfig data array.");
        }

        return new self(
            days: $data['days'] ?? []
        );
    }

    public function getNextRecurrence(\Carbon\CarbonInterface $current): \Carbon\CarbonInterface
    {
        return $this->getNextWeeklyOccurrence($current, $this->days);
    }

    public function shouldCreateInstance(\Carbon\CarbonInterface $date): bool
    {
        return empty($this->days) || in_array(strtolower($date->format('l')), $this->days);
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

        return in_array($date->dayOfWeek, $allowedDayNumbers);
    }

    protected function getNextWeeklyOccurrence(\Carbon\CarbonInterface $current, array $allowedDays): \Carbon\CarbonInterface
    {
        $next = $current->copy()->addDay();

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
        while (! in_array($next->dayOfWeek, $allowedDayNumbers)) {
            $next = $next->addDay();

            // Prevent infinite loop
            if ($next->diffInDays($current) > 7) {
                break;
            }
        }

        return $next;
    }
}
