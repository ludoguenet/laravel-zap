<?php

use Carbon\Carbon;
use Zap\Exceptions\InvalidScheduleException;
use Zap\Exceptions\ScheduleConflictException;
use Zap\Facades\Zap;

test('"missing start date" validation passed successfully', function () {
    Zap::for(createUser())
        ->named('Test Schedule')
        ->from(today()->addDay()->toDateString())
        ->addPeriod('09:00', '10:00')
        ->save();
})->throwsNoExceptions();

test('"missing start date" validation failed successfully', function () {
    Zap::for(createUser())
        ->named('Test Schedule')
        ->addPeriod('09:00', '10:00')
        ->save();
})->throws(InvalidArgumentException::class, 'Start date must be set using from() method');

test('"invalid time format" validation passed successfully', function () {
    Zap::for(createUser())
        ->from(today()->addDay()->toDateString())
        ->addPeriod('09:00', '10:00')
        ->save();
})->throwsNoExceptions();

test('"invalid time format" validation failed successfully', function () {
    Zap::for(createUser())
        ->from(today()->addDay()->toDateString())
        ->addPeriod('9am', '10am')
        ->save();
})->throws(InvalidScheduleException::class, "Schedule validation failed with 2 errors:\n• periods.0.start_time: Invalid start time format '9am'. Please use HH:MM format (e.g., 09:30)\n• periods.0.end_time: Invalid end time format '10am'. Please use HH:MM format (e.g., 17:30)");

test('"end time before start time" validation passed successfully', function () {
    Zap::for(createUser())
        ->from(today()->addDay()->toDateString())
        ->addPeriod('09:00', '10:00')
        ->save();
})->throwsNoExceptions();

test('"end time before start time" validation failed successfully', function () {
    Zap::for(createUser())
        ->from(today()->addDay()->toDateString())
        ->addPeriod('10:00', '09:00')
        ->save();
})->throws(InvalidScheduleException::class, "Schedule validation failed with 1 error:\n• periods.0.end_time: End time (09:00) must be after start time (10:00)");

test('"period too short" validation passed successfully', function () {
    config([
        'zap.default_rules.max_duration.enabled' => true,
        'zap.validation.min_period_duration' => 15,
    ]);

    Zap::for(createUser())
        ->from(today()->addDay()->toDateString())
        ->addPeriod('09:00', '09:15')
        ->save();
})->throwsNoExceptions();

test('"period too short" validation failed successfully', function () {
    config([
        'zap.default_rules.max_duration.enabled' => true,
        'zap.validation.min_period_duration' => 15,
    ]);

    Zap::for(createUser())
        ->from(today()->addDay()->toDateString())
        ->addPeriod('09:00', '09:10')
        ->save();
})->throws(InvalidScheduleException::class, "Schedule validation failed with 1 error:\n• periods.0.duration: Period is too short (10 minutes). Minimum duration is 15 minutes");

test('"period too long" validation passed successfully', function () {
    config(['zap.default_rules.max_duration.enabled' => true]);

    Zap::for(createUser())
        ->from(today()->addDay()->toDateString())
        ->addPeriod('09:00', '17:00') // 480 minutes
        ->maxDuration(480)
        ->save();
})->throwsNoExceptions();

test('"period too long" validation failed successfully', function () {
    config(['zap.default_rules.max_duration.enabled' => true]);

    Zap::for(createUser())
        ->from(today()->addDay()->toDateString())
        ->addPeriod('09:00', '17:01') // 481 minutes
        ->maxDuration(480)
        ->save();
})->throws(InvalidScheduleException::class, "Schedule validation failed with 2 errors:\n• periods.0.duration: Period is too long (481 minutes). Maximum duration is 480 minutes\n• periods.0.max_duration: Period 09:00-17:01 is too long (8 hours). Maximum allowed is 8 hours");

test('"overlapping periods within same schedule" validation passed successfully', function () {
    config(['zap.validation.allow_overlapping_periods' => false]);

    Zap::for(createUser())
        ->from(today()->addDay()->toDateString())
        ->addPeriod('09:00', '11:00')
        ->addPeriod('11:00', '12:00')
        ->save();
})->throwsNoExceptions();

test('"overlapping periods within same schedule" validation failed successfully', function () {
    config(['zap.validation.allow_overlapping_periods' => false]);

    Zap::for(createUser())
        ->from(today()->addDay()->toDateString())
        ->addPeriod('09:00', '11:00')
        ->addPeriod('10:00', '12:00')
        ->save();
})->throws(InvalidScheduleException::class, "Schedule validation failed with 1 error:\n• periods.0.overlap: Period 0 (09:00-11:00) overlaps with period 1 (10:00-12:00)");

test('"working hours" validation passed successfully', function () {
    Zap::for(createUser())
        ->from(today()->addDay()->toDateString())
        ->addPeriod('09:00', '17:00')
        ->workingHoursOnly('09:00', '17:00')
        ->save();
})->throwsNoExceptions();

test('"working hours" validation failed successfully', function () {
    Zap::for(createUser())
        ->from(today()->addDay()->toDateString())
        ->addPeriod('08:00', '09:00')
        ->workingHoursOnly('09:00', '17:00')
        ->save();
})->throws(InvalidScheduleException::class, "Schedule validation failed with 1 error:\n• periods.0.working_hours: Period 08:00-09:00 is outside working hours (09:00-17:00)");

test('"weekend" validation passed successfully', function (int $day) {
    config(['zap.default_rules.no_weekends.enabled' => true]);

    Zap::for(createUser())
        ->from(today()->next($day)->toDateString())
        ->addPeriod('09:00', '10:00')
        ->noWeekends()
        ->save();
})->throwsNoExceptions()->with([
    'monday' => Carbon::MONDAY,
    'tuesday' => Carbon::TUESDAY,
    'wednesday' => Carbon::WEDNESDAY,
    'thursday' => Carbon::THURSDAY,
    'friday' => Carbon::FRIDAY,
]);

test('"weekend" validation failed successfully', function (int $day) {
    config(['zap.default_rules.no_weekends.enabled' => true]);

    $date = today()->next($day);

    expect(function () use ($date) {
        Zap::for(createUser())
            ->from($date->toDateString())
            ->addPeriod('09:00', '10:00')
            ->noWeekends()
            ->save();
    })->toThrow(InvalidScheduleException::class, "Schedule validation failed with 2 errors:\n• start_date: Schedule cannot start on {$date->format('l')}. Weekend schedules are not allowed\n• periods.0.date: Period cannot be scheduled on {$date->format('l')}. Weekend periods are not allowed");
})->with([
    'saturday' => Carbon::SATURDAY,
    'sunday' => Carbon::SUNDAY,
]);

test('"past date" validation passed successfully', function () {
    config(['zap.validation.require_future_dates' => true]);

    Zap::for(createUser())
        ->from(today()->addDay()->toDateString())
        ->addPeriod('09:00', '10:00')
        ->save();
})->throwsNoExceptions();

test('"past date" validation failed successfully', function () {
    config(['zap.validation.require_future_dates' => true]);

    Zap::for(createUser())
        ->from(today()->subDay()->toDateString())
        ->addPeriod('09:00', '10:00')
        ->save();
})->throws(InvalidScheduleException::class, "Schedule validation failed with 1 error:\n• start_date: The schedule cannot be created in the past. Please choose a future date");

test('"schedule conflicts" validation passed successfully', function () {
    $user = createUser();
    $futureDate = today()->addWeek()->toDateString();

    Zap::for($user)
        ->named('First Meeting')
        ->from($futureDate)
        ->addPeriod('09:00', '10:00')
        ->save();

    Zap::for($user)
        ->named('Second Meeting')
        ->from($futureDate)
        ->addPeriod('10:00', '10:30')
        ->save();
})->throwsNoExceptions();

test('"schedule conflicts" validation failed successfully', function () {
    $user = createUser();
    $futureDate = today()->addWeek()->toDateString();

    Zap::for($user)
        ->named('First Meeting')
        ->from($futureDate)
        ->addPeriod('09:00', '10:00')
        ->save();

    expect(function () use ($user, $futureDate) {
        Zap::for($user)
            ->named('Second Meeting')
            ->from($futureDate)
            ->addPeriod('09:30', '10:30')
            ->noOverlap()
            ->save();
    })->toThrow(ScheduleConflictException::class, "Schedule conflict detected! 'New schedule' conflicts with existing schedule 'First Meeting'.");
});
