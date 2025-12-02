<?php

namespace Zap\Builders;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Zap\Data\AnnuallyFrequencyConfig;
use Zap\Data\BiMonthlyFrequencyConfig;
use Zap\Data\BiWeeklyFrequencyConfig;
use Zap\Data\DailyFrequencyConfig;
use Zap\Data\FrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig;
use Zap\Data\QuarterlyFrequencyConfig;
use Zap\Data\SemiAnnuallyFrequencyConfig;
use Zap\Data\WeeklyFrequencyConfig;
use Zap\Enums\Frequency;
use Zap\Enums\ScheduleTypes;
use Zap\Models\Schedule;
use Zap\Services\ScheduleService;

class ScheduleBuilder
{
    private ?Model $schedulable = null;

    private array $attributes = [];

    private array $periods = [];

    private array $rules = [];

    /**
     * Set the schedulable model (User, etc.)
     */
    public function for(Model $schedulable): self
    {
        $this->schedulable = $schedulable;

        return $this;
    }

    /**
     * Set the schedule name.
     */
    public function named(string $name): self
    {
        $this->attributes['name'] = $name;

        return $this;
    }

    /**
     * Set the schedule description.
     */
    public function description(string $description): self
    {
        $this->attributes['description'] = $description;

        return $this;
    }

    /**
     * Set the start date.
     */
    public function from(CarbonInterface|string $startDate): self
    {
        $this->attributes['start_date'] = $startDate instanceof CarbonInterface
            ? $startDate->toDateString()
            : $startDate;

        return $this;
    }

    /**
     * Alias of from()
     */
    public function on(CarbonInterface|string $startDate): self
    {
        return $this->from($startDate);
    }

    /**
     * Set the end date.
     */
    public function to(CarbonInterface|string|null $endDate): self
    {
        $this->attributes['end_date'] = $endDate instanceof CarbonInterface
            ? $endDate->toDateString()
            : $endDate;

        return $this;
    }

    /**
     * Set the date for a specific year.
     */
    public function forYear(int $year): self
    {
        $this
            ->from("$year-01-01")
            ->to("$year-12-31");

        return $this;
    }

    /**
     * Set both start and end dates.
     */
    public function between(CarbonInterface|string $start, CarbonInterface|string $end): self
    {
        return $this->from($start)->to($end);
    }

    /**
     * Add a time period to the schedule.
     */
    public function addPeriod(string $startTime, string $endTime, ?CarbonInterface $date = null): self
    {
        $this->periods[] = [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'date' => $date?->toDateString() ?? $this->attributes['start_date'] ?? now()->toDateString(),
        ];

        return $this;
    }

    /**
     * Add multiple periods at once.
     */
    public function addPeriods(array $periods): self
    {
        foreach ($periods as $period) {
            $this->addPeriod(
                $period['start_time'],
                $period['end_time'],
                isset($period['date']) ? Carbon::parse($period['date']) : null
            );
        }

        return $this;
    }

    /**
     * Set schedule as daily recurring.
     */
    public function daily(): self
    {
        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = Frequency::DAILY;
        $this->attributes['frequency_config'] = new DailyFrequencyConfig;

        return $this;
    }

    /**
     * Set schedule as weekly recurring.
     */
    public function weekly(array $days = []): self
    {
        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = Frequency::WEEKLY;
        $this->attributes['frequency_config'] = WeeklyFrequencyConfig::fromArray([
            'days' => $days,
        ]);

        return $this;
    }

    /**
     * Set schedule as bi-weekly recurring.
     */
    public function biweekly(array $days = [], CarbonInterface|string|null $startsOn = null): self
    {
        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = Frequency::BIWEEKLY;
        $this->attributes['frequency_config'] = BiWeeklyFrequencyConfig::fromArray([
            'days' => $days,
            'startsOn' => $startsOn,
        ]);

        return $this;
    }

    /**
     * Set schedule as monthly recurring.
     */
    public function monthly(array $config = []): self
    {
        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = Frequency::MONTHLY;
        $this->attributes['frequency_config'] = MonthlyFrequencyConfig::fromArray(
            $config
        );

        return $this;
    }

    /**
     * Set schedule as bi-monthly recurring.
     */
    public function bimonthly(array $config = []): self
    {
        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = Frequency::BIMONTHLY;
        $this->attributes['frequency_config'] = BiMonthlyFrequencyConfig::fromArray(
            $config
        );

        return $this;
    }

    /**
     * Set schedule as quarterly recurring.
     */
    public function quarterly(array $config = []): self
    {
        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = Frequency::QUARTERLY;
        $this->attributes['frequency_config'] = QuarterlyFrequencyConfig::fromArray(
            $config
        );

        return $this;
    }

    /**
     * Set schedule as semi-annually recurring.
     */
    public function semiannually(array $config = []): self
    {
        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = Frequency::SEMIANNUALLY;
        $this->attributes['frequency_config'] = SemiAnnuallyFrequencyConfig::fromArray(
            $config
        );

        return $this;
    }

    /**
     * Set schedule as annually recurring.
     */
    public function annually(array $config = []): self
    {
        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = Frequency::ANNUALLY;
        $this->attributes['frequency_config'] = AnnuallyFrequencyConfig::fromArray(
            $config
        );

        return $this;
    }

    /**
     * Set custom recurring frequency.
     */
    public function recurring(string|Frequency $frequency, array|FrequencyConfig $config = []): self
    {
        // Check if frequency is a valid enum value and convert config accordingly for backward compatibility
        if (is_string($frequency)) {
            $frequency = Frequency::tryFrom($frequency) ?? $frequency;
            if ($frequency instanceof Frequency) {
                $configClass = $frequency->configClass();
                if ($config instanceof FrequencyConfig && ! ($config instanceof $configClass)) {
                    throw new \InvalidArgumentException("Invalid config class for frequency {$frequency->value}. Expected ".$configClass);
                }
                $config = $config instanceof $configClass ? $config :
                    $frequency->configClass()::fromArray($config);
            }
        }

        $this->attributes['is_recurring'] = true;
        $this->attributes['frequency'] = $frequency;
        $this->attributes['frequency_config'] = $config;

        return $this;
    }

    /**
     * Add a validation rule.
     */
    public function withRule(string $ruleName, array $config = []): self
    {
        $this->rules[$ruleName] = $config;

        return $this;
    }

    /**
     * Add no overlap rule.
     */
    public function noOverlap(): self
    {
        return $this->withRule('no_overlap');
    }

    /**
     * Set schedule as availability type (allows overlaps).
     */
    public function availability(): self
    {
        $this->attributes['schedule_type'] = ScheduleTypes::AVAILABILITY;

        return $this;
    }

    /**
     * Set schedule as appointment type (prevents overlaps).
     */
    public function appointment(): self
    {
        $this->attributes['schedule_type'] = ScheduleTypes::APPOINTMENT;

        return $this;
    }

    /**
     * Set schedule as blocked type (prevents overlaps).
     */
    public function blocked(): self
    {
        $this->attributes['schedule_type'] = ScheduleTypes::BLOCKED;

        return $this;
    }

    /**
     * Set schedule as custom type.
     */
    public function custom(): self
    {
        $this->attributes['schedule_type'] = ScheduleTypes::CUSTOM;

        return $this;
    }

    /**
     * Set schedule type explicitly.
     */
    public function type(string $type): self
    {
        try {
            $scheduleType = ScheduleTypes::from($type);
        } catch (\ValueError) {
            throw new \InvalidArgumentException("Invalid schedule type: {$type}. Valid types are: ".implode(', ', ScheduleTypes::values()));
        }

        $this->attributes['schedule_type'] = $scheduleType;

        return $this;
    }

    /**
     * Add working hours only rule.
     */
    public function workingHoursOnly(string $start = '09:00', string $end = '17:00'): self
    {
        return $this->withRule('working_hours', compact('start', 'end'));
    }

    /**
     * Add maximum duration rule.
     */
    public function maxDuration(int $minutes): self
    {
        return $this->withRule('max_duration', ['minutes' => $minutes]);
    }

    /**
     * Add no weekends rule.
     */
    public function noWeekends(): self
    {
        return $this->withRule('no_weekends');
    }

    /**
     * Add custom metadata.
     */
    public function withMetadata(array $metadata): self
    {
        $this->attributes['metadata'] = array_merge($this->attributes['metadata'] ?? [], $metadata);

        return $this;
    }

    /**
     * Set the schedule as inactive.
     */
    public function inactive(): self
    {
        $this->attributes['is_active'] = false;

        return $this;
    }

    /**
     * Set the schedule as active (default).
     */
    public function active(): self
    {
        $this->attributes['is_active'] = true;

        return $this;
    }

    /**
     * Build and validate the schedule without saving.
     */
    public function build(): array
    {
        if (! $this->schedulable) {
            throw new \InvalidArgumentException('Schedulable model must be set using for() method');
        }

        if (empty($this->attributes['start_date'])) {
            throw new \InvalidArgumentException('Start date must be set using from() method');
        }

        // Set default schedule_type if not specified
        if (! isset($this->attributes['schedule_type'])) {
            $this->attributes['schedule_type'] = ScheduleTypes::CUSTOM;
        }

        if (isset($this->attributes['frequency_config']) && $this->attributes['frequency_config'] instanceof FrequencyConfig) {
            $this->attributes['frequency_config']->setStartFromStartDate(
                Carbon::parse($this->attributes['start_date'])
            );
        }

        return [
            'schedulable' => $this->schedulable,
            'attributes' => $this->attributes,
            'periods' => $this->periods,
            'rules' => $this->rules,
        ];
    }

    /**
     * Save the schedule.
     */
    public function save(): Schedule
    {
        $built = $this->build();

        return app(ScheduleService::class)->create(
            $built['schedulable'],
            $built['attributes'],
            $built['periods'],
            $built['rules']
        );
    }

    /**
     * Get the current attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get the current periods.
     */
    public function getPeriods(): array
    {
        return $this->periods;
    }

    /**
     * Get the current rules.
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Reset the builder to start fresh.
     */
    public function reset(): self
    {
        $this->schedulable = null;
        $this->attributes = [];
        $this->periods = [];
        $this->rules = [];

        return $this;
    }

    /**
     * Clone the builder with the same configuration.
     */
    public function clone(): self
    {
        $clone = new self;
        $clone->schedulable = $this->schedulable;
        $clone->attributes = $this->attributes;
        $clone->periods = $this->periods;
        $clone->rules = $this->rules;

        return $clone;
    }
}
