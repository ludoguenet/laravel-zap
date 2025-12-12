<?php

use Zap\Builders\ScheduleBuilder;
use Zap\Facades\Zap;
use Zap\Models\Schedule;
use Zap\Services\ScheduleService;

it('zap helper function exists and is callable', function () {
    expect(function_exists('zap'))->toBeTrue();
    expect(is_callable('zap'))->toBeTrue();
});

it('zap helper returns ScheduleService instance', function () {
    $service = zap();

    expect($service)->toBeInstanceOf(ScheduleService::class);
});

it('zap helper returns same instance as facade', function () {
    $helperInstance = zap();
    $facadeInstance = Zap::getFacadeRoot();

    expect($helperInstance)->toBe($facadeInstance);
});

it('zap helper for method returns ScheduleBuilder', function () {
    $user = createUser();

    $builder = zap()->for($user);

    expect($builder)->toBeInstanceOf(ScheduleBuilder::class);
});

it('zap helper for method works same as facade for', function () {
    $user = createUser();

    $helperBuilder = zap()->for($user);
    $facadeBuilder = Zap::for($user);

    expect($helperBuilder)->toBeInstanceOf(ScheduleBuilder::class);
    expect($facadeBuilder)->toBeInstanceOf(ScheduleBuilder::class);
});

it('zap helper can create schedule using for method', function () {
    $user = createUser();

    $schedule = zap()->for($user)
        ->named('Helper Test Schedule')
        ->from('2025-01-01')
        ->addPeriod('09:00', '10:00')
        ->save();

    expect($schedule)->toBeInstanceOf(Schedule::class);
    expect($schedule->name)->toBe('Helper Test Schedule');
    expect($schedule->start_date->format('Y-m-d'))->toBe('2025-01-01');
    expect($schedule->periods)->toHaveCount(1);
});

it('zap helper schedule method returns ScheduleBuilder', function () {
    $builder = zap()->schedule();

    expect($builder)->toBeInstanceOf(ScheduleBuilder::class);
});

it('zap helper schedule method works same as facade schedule', function () {
    $helperBuilder = zap()->schedule();
    $facadeBuilder = Zap::schedule();

    expect($helperBuilder)->toBeInstanceOf(ScheduleBuilder::class);
    expect($facadeBuilder)->toBeInstanceOf(ScheduleBuilder::class);
});

it('zap helper can create schedule using schedule method', function () {
    $user = createUser();

    $schedule = zap()->schedule()
        ->for($user)
        ->named('Schedule Method Test')
        ->from('2025-01-01')
        ->addPeriod('10:00', '11:00')
        ->save();

    expect($schedule)->toBeInstanceOf(Schedule::class);
    expect($schedule->name)->toBe('Schedule Method Test');
    expect($schedule->periods)->toHaveCount(1);
});

it('zap helper and facade create equivalent schedules', function () {
    $user = createUser();

    $helperSchedule = zap()->for($user)
        ->named('Helper Schedule')
        ->from('2025-01-01')
        ->addPeriod('09:00', '10:00')
        ->save();

    $facadeSchedule = Zap::for($user)
        ->named('Facade Schedule')
        ->from('2025-01-01')
        ->addPeriod('10:00', '11:00')
        ->save();

    expect($helperSchedule)->toBeInstanceOf(Schedule::class);
    expect($facadeSchedule)->toBeInstanceOf(Schedule::class);
    expect($helperSchedule->schedulable_id)->toBe($facadeSchedule->schedulable_id);
    expect($helperSchedule->schedulable_type)->toBe($facadeSchedule->schedulable_type);
});

it('zap helper works with recurring schedules', function () {
    $user = createUser();

    $schedule = zap()->for($user)
        ->named('Recurring Helper Schedule')
        ->from('2025-01-01')
        ->to('2025-12-31')
        ->addPeriod('09:00', '10:00')
        ->weekly(['monday', 'wednesday'])
        ->save();

    expect($schedule->is_recurring)->toBeTrue();
    expect($schedule->frequency)->toBe(\Zap\Enums\Frequency::WEEKLY);
});

it('zap helper works with findConflicts method', function () {
    $user = createUser();

    $schedule = zap()->for($user)
        ->from('2025-01-01')
        ->addPeriod('09:00', '10:00')
        ->noOverlap()
        ->save();

    $conflicts = zap()->findConflicts($schedule);

    expect($conflicts)->toBeArray();
});

it('zap helper works with hasConflicts method', function () {
    $user = createUser();

    $schedule = zap()->for($user)
        ->from('2025-01-01')
        ->addPeriod('09:00', '10:00')
        ->noOverlap()
        ->save();

    $hasConflicts = zap()->hasConflicts($schedule);

    expect($hasConflicts)->toBeBool();
});

it('zap helper returns same service instance on multiple calls', function () {
    $first = zap();
    $second = zap();

    expect($first)->toBe($second);
});

it('zap helper can be used in method chaining', function () {
    $user = createUser();

    $schedule = zap()
        ->for($user)
        ->named('Chained Helper')
        ->from('2025-01-01')
        ->addPeriod('09:00', '10:00')
        ->save();

    expect($schedule)->toBeInstanceOf(Schedule::class);
    expect($schedule->name)->toBe('Chained Helper');
});
