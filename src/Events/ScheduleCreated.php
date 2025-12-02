<?php

namespace Zap\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Zap\Enums\Frequency;
use Zap\Models\Schedule;

class ScheduleCreated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Schedule $schedule
    ) {}

    /**
     * Get the schedule that was created.
     */
    public function getSchedule(): Schedule
    {
        return $this->schedule;
    }

    /**
     * Get the schedulable model.
     */
    public function getSchedulable()
    {
        return $this->schedule->schedulable;
    }

    /**
     * Check if the schedule is recurring.
     */
    public function isRecurring(): bool
    {
        return $this->schedule->is_recurring;
    }

    /**
     * Get the event as an array.
     */
    public function toArray(): array
    {
        return [
            'schedule_id' => $this->schedule->id,
            'schedulable_type' => $this->schedule->schedulable_type,
            'schedulable_id' => $this->schedule->schedulable_id,
            'name' => $this->schedule->name,
            'start_date' => $this->schedule->start_date->toDateString(),
            'end_date' => $this->schedule->end_date?->toDateString(),
            'is_recurring' => $this->schedule->is_recurring,
            'frequency' => $this->schedule->frequency instanceof Frequency ? $this->schedule->frequency->value : $this->schedule->frequency,
            'created_at' => $this->schedule->created_at->toISOString(),
        ];
    }
}
