<?php

if (! function_exists('zap')) {
    /**
     * Get the Zap service instance.
     */
    function zap(): \Zap\Services\ScheduleService
    {
        return app('zap');
    }
}
