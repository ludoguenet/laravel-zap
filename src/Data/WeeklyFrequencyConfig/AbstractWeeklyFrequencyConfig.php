<?php

namespace Zap\Data\WeeklyFrequencyConfig;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Zap\Data\FrequencyConfig;
use Zap\Models\Schedule;

/**
 * @property-read list<string> $daysOfWeek
 *
 * @phpstan-consistent-constructor
 */
abstract class AbstractWeeklyFrequencyConfig extends FrequencyConfig
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

        return new static(
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

    public function shouldCreateInstance(CarbonInterface $date): bool
    {
        $dayMatches = empty($this->days) || in_array(strtolower($date->format('l')), $this->days);

        if ($this->startsOn === null) {
            return $dayMatches;
        }

        return $dayMatches && $this->startsOn->diffInWeeks($date) % static::getFrequency() === 0;
    }

    public function shouldCreateRecurringInstance(Schedule $schedule, CarbonInterface $date): bool
    {
        if ($this->startsOn === null) {
            $this->setStartFromStartDate($schedule->start_date);
        }

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
            $this->startsOn->diffInWeeks($date) % static::getFrequency() === 0;
    }

    public function getNextRecurrence(CarbonInterface $current): CarbonInterface
    {
        return $this->getNextBiWeeklyOccurrence($current, $this->days);
    }

    protected function getNextBiWeeklyOccurrence(CarbonInterface $current, array $allowedDays): CarbonInterface
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
        while (! in_array($next->dayOfWeek, $allowedDayNumbers) || $this->startsOn->diffInWeeks($next) % static::getFrequency() !== 0) {
            $next = $next->addDay();

            // Prevent infinite loop
            if ($next->diffInDays($current) > static::getFrequency() * 7 * 2) {
                break;
            }
        }

        return $next;
    }

    /** @return int<1, 52> */
    abstract public static function getFrequency(): int;
}
