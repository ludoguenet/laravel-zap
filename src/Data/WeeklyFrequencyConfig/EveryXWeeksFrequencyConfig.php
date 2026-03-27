<?php

namespace Zap\Data\WeeklyFrequencyConfig;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Zap\Data\FrequencyConfig;
use Zap\Models\Schedule;

/**
 * Generic weekly frequency config that supports any number of weeks (1-52).
 * Uses instance-based frequency instead of static method.
 *
 * @property-read list<string> $daysOfWeek
 */
final class EveryXWeeksFrequencyConfig extends FrequencyConfig
{
    /** @var int<1, 52> */
    private int $frequencyWeeks;

    public ?CarbonInterface $startsOn = null;

    /**
     * @param  int<1, 52>  $frequencyWeeks
     */
    public function __construct(
        int $frequencyWeeks,
        public array $days = [],
        CarbonInterface|string|null $startsOn = null,
    ) {
        $this->frequencyWeeks = $frequencyWeeks;

        if ($startsOn === null) {
            return;
        }

        if (is_string($startsOn)) {
            $startsOn = Carbon::parse($startsOn);
        }

        $this->startsOn = $this->normalizeToAppTimezone($startsOn)->startOfWeek(
            config()->integer('zap.calendar.week_start', CarbonInterface::MONDAY)
        );
    }

    public static function fromArray(array $data): self
    {
        if (! array_key_exists('frequencyWeeks', $data)) {
            throw new \InvalidArgumentException("Missing 'frequencyWeeks' key in EveryXWeeksFrequencyConfig data array.");
        }

        if (! array_key_exists('days', $data) || ! is_array($data['days'])) {
            $data['days'] = [];
        }

        return new self(
            frequencyWeeks: (int) $data['frequencyWeeks'],
            days: $data['days'],
            startsOn: $data['startsOn'] ?? null,
        );
    }

    /**
     * @return int<1, 52>
     */
    public function getFrequencyWeeks(): int
    {
        return $this->frequencyWeeks;
    }

    public function setStartFromStartDate(CarbonInterface $startDate): self
    {
        if ($this->startsOn !== null) {
            return $this;
        }

        $this->startsOn = $this->normalizeToAppTimezone($startDate)->startOfWeek(
            config()->integer('zap.calendar.week_start', CarbonInterface::MONDAY)
        );

        return $this;
    }

    public function shouldCreateInstance(CarbonInterface $date): bool
    {
        $dayMatches = (empty($this->days) || in_array(strtolower($date->format('l')), $this->days));

        if ($this->startsOn === null) {
            return $dayMatches;
        }

        return $dayMatches && $this->startsOn->diffInWeeks($date) % $this->frequencyWeeks === 0;
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
                default => 1,
            };
        }, $allowedDays);

        return in_array($date->dayOfWeek, $allowedDayNumbers) &&
            $this->startsOn->diffInWeeks($date) % $this->frequencyWeeks === 0;
    }

    public function getNextRecurrence(CarbonInterface $current): CarbonInterface
    {
        $next = $current->copy()->addDay();
        $weekStart = config()->integer('zap.calendar.week_start', CarbonInterface::MONDAY);

        if ($this->startsOn === null) {
            $this->startsOn = $this->normalizeToAppTimezone($current)->startOfWeek($weekStart);
        }

        $allowedDays = $this->days;
        if (empty($allowedDays)) {
            $allowedDays = ['monday'];
        }

        $allowedDayNumbers = array_map(function ($day) {
            return match (strtolower($day)) {
                'sunday' => 0,
                'monday' => 1,
                'tuesday' => 2,
                'wednesday' => 3,
                'thursday' => 4,
                'friday' => 5,
                'saturday' => 6,
                default => 1,
            };
        }, $allowedDays);

        while (! in_array($next->dayOfWeek, $allowedDayNumbers) || $this->startsOn->diffInWeeks($next) % $this->frequencyWeeks !== 0) {
            $next = $next->addDay();

            if ($next->diffInDays($current) > $this->frequencyWeeks * 7 * 2) {
                break;
            }
        }

        return $next;
    }

    public function toArray(): array
    {
        return [
            'days' => $this->days,
            'startsOn' => $this->startsOn,
            'frequencyWeeks' => $this->frequencyWeeks,
        ];
    }

    private function normalizeToAppTimezone(CarbonInterface $date): CarbonInterface
    {
        $appTimezone = config('app.timezone', 'UTC');

        return Carbon::create(
            $date->year,
            $date->month,
            $date->day,
            0,
            0,
            0,
            $appTimezone
        );
    }
}
