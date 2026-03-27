<?php

namespace Zap\Data;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;
use Zap\Models\Schedule;

abstract class FrequencyConfig implements Arrayable
{
    abstract public static function fromArray(array $data): self;

    abstract public function getNextRecurrence(CarbonInterface $current): CarbonInterface;

    public function setStartFromStartDate(CarbonInterface $startDate): self
    {
        return $this;
    }

    abstract public function shouldCreateInstance(CarbonInterface $date): bool;

    abstract public function shouldCreateRecurringInstance(Schedule $schedule, CarbonInterface $date): bool;

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
