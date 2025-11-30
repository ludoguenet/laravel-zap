<?php

use Carbon\CarbonInterface;

return [
    /*
    |--------------------------------------------------------------------------
    | Calendar Configuration
    |--------------------------------------------------------------------------
    |
    | Settings related to calendar display and behavior.
    |
    */
    'calendar' => [
        'week_start' => CarbonInterface::MONDAY,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Schedule Rules
    |--------------------------------------------------------------------------
    |
    | These are the default validation rules that will be applied to all
    | schedules unless overridden during creation.
    |
    */
    'default_rules' => [
        'no_overlap' => [
            'enabled' => true,
            'applies_to' => [
                // Which schedule types get this rule automatically
                \Zap\Enums\ScheduleTypes::APPOINTMENT,
                \Zap\Enums\ScheduleTypes::BLOCKED,
            ],
        ],
        'working_hours' => [
            'enabled' => false,
            'start' => '09:00',
            'end' => '17:00',
        ],
        'max_duration' => [
            'enabled' => false,
            'minutes' => 480, // Maximum period duration in minutes if enabled
        ],
        'no_weekends' => [
            'enabled' => false,
            'saturday' => true,
            'sunday' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Conflict Detection
    |--------------------------------------------------------------------------
    |
    | Configure how schedule conflicts are detected and handled.
    |
    */
    'conflict_detection' => [
        'enabled' => true,
        'buffer_minutes' => 0, // Buffer time between schedules
    ],

    /*
    |--------------------------------------------------------------------------
    | Time Slots Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings for time slot generation and availability checking.
    |
    */
    'time_slots' => [
        'buffer_minutes' => 0, // Buffer time between sessions (e.g., 10 minutes between appointments)
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Configure custom validation rules and their settings.
    |
    */
    'validation' => [
        'require_future_dates' => true, // Schedules must be in the future
        'max_date_range' => 365, // Maximum days between start and end date
        'min_period_duration' => 15, // Minimum period duration in minutes
        'max_periods_per_schedule' => 50, // Maximum periods per schedule
        'allow_overlapping_periods' => false, // Allow periods to overlap within same schedule
    ],

];
