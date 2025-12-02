<?php

namespace Zap\Data;

use Illuminate\Contracts\Support\Arrayable;
use Zap\Models\Schedule;

abstract class FrequencyConfig implements Arrayable
{
    abstract public static function fromArray(array $data): self;

    abstract public function getNextRecurrence(\Carbon\CarbonInterface $current): \Carbon\CarbonInterface;

    public function setStartFromStartDate(\Carbon\CarbonInterface $startDate): self
    {
        return $this;
    }

    abstract public function shouldCreateInstance(\Carbon\CarbonInterface $date): bool;

    abstract public function shouldCreateRecurringInstance(Schedule $schedule, \Carbon\CarbonInterface $date): bool;

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
