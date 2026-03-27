<?php

namespace Zap\Facades;

use Illuminate\Support\Facades\Facade;
use Zap\Builders\ScheduleBuilder;
use Zap\Services\ScheduleService;

/**
 * @method static ScheduleBuilder for(mixed $schedulable)
 * @method static ScheduleBuilder schedule()
 * @method static array findConflicts(\Zap\Models\Schedule $schedule)
 * @method static bool hasConflicts(\Zap\Models\Schedule $schedule)
 *
 * @see ScheduleService
 */
class Zap extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'zap';
    }
}
