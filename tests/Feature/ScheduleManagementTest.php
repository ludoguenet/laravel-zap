<?php

use Zap\Enums\Frequency;
use Zap\Exceptions\InvalidScheduleException;
use Zap\Exceptions\ScheduleConflictException;
use Zap\Facades\Zap;
use Zap\Models\Schedule;

it('can create a basic schedule', function () {
    $user = createUser();

    $schedule = Zap::for($user)
        ->named('Test Schedule')
        ->from('2025-01-01')
        ->addPeriod('09:00', '10:00')
        ->save();

    expect($schedule)->toBeInstanceOf(Schedule::class);
    expect($schedule->name)->toBe('Test Schedule');
    expect($schedule->start_date->format('Y-m-d'))->toBe('2025-01-01');
    expect($schedule->periods)->toHaveCount(1);
});

it('can create recurring weekly schedule', function () {
    $user = createUser();

    $schedule = Zap::for($user)
        ->named('Weekly Meeting')
        ->from('2025-01-01')
        ->to('2025-12-31')
        ->addPeriod('09:00', '10:00')
        ->weekly(['monday', 'wednesday', 'friday'])
        ->save();

    expect($schedule->is_recurring)->toBeTrue();
    expect($schedule->frequency)->toBe(Frequency::WEEKLY);
    expect($schedule->frequency_config->toArray())->toBe(['days' => ['monday', 'wednesday', 'friday']]);
});

it('detects schedule conflicts', function () {
    $user = createUser();

    // Create first schedule
    Zap::for($user)
        ->from('2025-01-01')
        ->addPeriod('09:00', '10:00')
        ->noOverlap()
        ->save();

    // Try to create conflicting schedule
    expect(function () use ($user) {
        Zap::for($user)
            ->from('2025-01-01')
            ->addPeriod('09:30', '10:30') // Overlaps with first schedule
            ->noOverlap()
            ->save();
    })->toThrow(ScheduleConflictException::class);
});

it('can check availability', function () {
    $user = createUser();

    // Create a schedule
    Zap::for($user)
        ->from('2025-01-01')
        ->addPeriod('09:00', '10:00')
        ->save();

    // Check availability
    expect($user->isAvailableAt('2025-01-01', '09:00', '10:00'))->toBeFalse();
    expect($user->isAvailableAt('2025-01-01', '10:00', '11:00'))->toBeTrue();
});

it('can get bookable slots', function () {
    $user = createUser();

    // Create availability schedule
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '12:00')
        ->save();

    // Create a schedule that blocks 09:00-10:00
    Zap::for($user)
        ->from('2025-01-01')
        ->addPeriod('09:00', '10:00')
        ->save();

    $slots = $user->getBookableSlots('2025-01-01', 60);

    expect($slots)->toBeArray();
    expect($slots[0]['is_available'])->toBeFalse(); // 09:00-10:00 should be unavailable
    expect($slots[1]['is_available'])->toBeTrue();  // 10:00-11:00 should be available
    expect($slots[2]['is_available'])->toBeTrue();  // 11:00-12:00 should be available
});

it('can check if a date is bookable using isBookableAt', function () {
    $user = createUser();

    // Create availability schedule
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '12:00')
        ->save();

    // Initially, the date should be bookable (no blocking schedules)
    expect($user->isBookableAt('2025-01-01', 60))->toBeTrue();

    // Block all availability on that date
    Zap::for($user)
        ->from('2025-01-01')
        ->addPeriod('09:00', '12:00')
        ->save();

    // With a full blocking schedule, no bookable slots should remain
    expect($user->isBookableAt('2025-01-01', 60))->toBeFalse();
});

describe('can check if a date time is bookable using isBookableAtTime', function () {

    it('returns true when the requested time fits inside a bookable slot', function () {
        $user = createUser();
        $availability = Zap::for($user)
            ->availability()
            ->forYear(2025)
            ->addPeriod('09:00', '12:00')
            ->addPeriod('14:00', '17:00')
            ->weeklyEven(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
            ->save();

        $isBookable = $user->isBookableAtTime('2025-01-06', '9:00', '9:30');

        expect($isBookable)->toBeTrue();
    });

    it('returns false when no bookable slots exist for the requested date', function () {
        $user = createUser();

        $availability = Zap::for($user)
            ->availability()
            ->forYear(2025)
            ->addPeriod('09:00', '12:00')
            ->addPeriod('14:00', '17:00')
            ->weeklyEven(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
            ->save();

        $isBookable1 = $user->isBookableAtTime('2025-01-01', '9:00', '9:30');

        $isBookable2 = $user->isBookableAtTime('2028-01-01', '9:00', '9:30');

        expect($isBookable1)->toBeFalse();
        expect($isBookable2)->toBeFalse();

    });

    it('returns false when an appointment already exists at the requested time', function () {
        $user = createUser();

        // Create an availability for even weeks
        Zap::for($user)
            ->availability()
            ->forYear(2025)
            ->addPeriod('09:00', '12:00')
            ->weeklyEven(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
            ->save();

        // Create an appointment inside the bookable range
        $appointment = Zap::for($user)
            ->appointment()
            ->on('2025-01-06') // Monday week 2 → even
            ->addPeriod('09:00', '09:30')
            ->save();

        $custom = Zap::for($user)
            ->custom()
            ->on('2025-01-06') // Monday week 2 → even
            ->addPeriod('11:00', '11:30')
            ->save();

        // Check if the user is bookable on the same slot
        $isBookable1 = $user->isBookableAtTime('2025-01-06', '09:00', '10:00');

        $isBookable2 = $user->isBookableAtTime('2025-01-06', '11:00', '11:30');

        expect($isBookable1)->toBeFalse();
        expect($isBookable2)->toBeFalse();

    });

});

it('can find next bookable slot', function () {
    $user = createUser();

    // Create availability schedule
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '17:00')
        ->save();

    // Create a schedule that blocks the morning
    Zap::for($user)
        ->from('2025-01-01')
        ->addPeriod('09:00', '12:00')
        ->save();

    $nextSlot = $user->getNextBookableSlot('2025-01-01', 60);

    expect($nextSlot)->toBeArray();
    expect($nextSlot['date'])->toBe('2025-01-01');
    expect($nextSlot['start_time'])->toBe('12:00'); // First available after the blocked period
});

it('respects working hours rule', function () {
    $user = createUser();

    expect(function () use ($user) {
        Zap::for($user)
            ->from('2025-01-01')
            ->addPeriod('18:00', '19:00') // Outside working hours
            ->workingHoursOnly('09:00', '17:00')
            ->save();
    })->toThrow(InvalidScheduleException::class);
});

it('can handle schedule metadata', function () {
    $user = createUser();

    $schedule = Zap::for($user)
        ->named('Meeting with Client')
        ->description('Important client meeting')
        ->from('2025-01-01')
        ->addPeriod('09:00', '10:00')
        ->withMetadata([
            'location' => 'Conference Room A',
            'attendees' => ['john@example.com', 'jane@example.com'],
            'priority' => 'high',
        ])
        ->save();

    expect($schedule->metadata['location'])->toBe('Conference Room A');
    expect($schedule->metadata['attendees'])->toHaveCount(2);
    expect($schedule->metadata['priority'])->toBe('high');
});

it('can create complex recurring schedule', function () {
    $user = createUser();

    $schedule = Zap::for($user)
        ->named('Office Hours')
        ->description('Available for consultations')
        ->from('2025-01-01')
        ->to('2025-06-30')
        ->addPeriod('09:00', '12:00') // Morning session
        ->addPeriod('14:00', '17:00') // Afternoon session
        ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
        ->noWeekends()
        ->save();

    expect($schedule->is_recurring)->toBeTrue();
    expect($schedule->frequency)->toBe(Frequency::WEEKLY);
    expect($schedule->periods)->toHaveCount(2);
    expect($schedule->description)->toBe('Available for consultations');
});

it('can validate schedule periods', function () {
    $user = createUser();

    expect(function () use ($user) {
        Zap::for($user)
            ->from('2025-01-01')
            ->addPeriod('10:00', '09:00') // End time before start time
            ->save();
    })->toThrow(InvalidScheduleException::class);
});

it('can check for schedule conflicts without saving', function () {
    $user = createUser();

    // Create first schedule
    $schedule1 = Zap::for($user)
        ->from('2025-01-01')
        ->addPeriod('09:00', '10:00')
        ->save();

    // Create another schedule that would conflict
    $schedule2 = Zap::for($user)
        ->from('2025-01-01')
        ->addPeriod('09:30', '10:30')
        ->build();

    $conflicts = Zap::findConflicts($schedule1);
    expect($conflicts)->toBeArray();
});

it('supports different schedulable types', function () {
    $user = createUser();
    $room = createRoom();

    // Schedule for user
    $userSchedule = Zap::for($user)
        ->named('User Meeting')
        ->from('2025-01-01')
        ->addPeriod('09:00', '10:00')
        ->save();

    // Schedule for room
    $roomSchedule = Zap::for($room)
        ->named('Room Booking')
        ->from('2025-01-01')
        ->addPeriod('11:00', '12:00')
        ->save();

    expect($userSchedule->schedulable_type)->toBe('users');
    expect($roomSchedule->schedulable_type)->toBe('rooms');
    expect($userSchedule->schedulable_id)->toBe($user->getKey());
    expect($roomSchedule->schedulable_id)->toBe($room->getKey());
});

it('can handle monthly recurring schedules', function () {
    $user = createUser();

    $schedule = Zap::for($user)
        ->named('Monthly Review')
        ->from('2025-01-01')
        ->to('2025-12-31')
        ->addPeriod('14:00', '15:00')
        ->monthly(['day_of_month' => 1])
        ->save();

    expect($schedule->is_recurring)->toBeTrue();
    expect($schedule->frequency)->toBe(Frequency::MONTHLY);
    expect($schedule->frequency_config->toArray())->toBe(['days_of_month' => [1]]);
});

it('can create schedules with extended recurring frequencies', function () {
    $user = createUser();

    $biweekly = Zap::for($user)
        ->named('Bi-Weekly Check-in')
        ->from('2025-01-06')
        ->to('2025-02-28')
        ->addPeriod('09:00', '10:00')
        ->biweekly(['monday'])
        ->save();

    $bimonthly = Zap::for($user)
        ->named('Bi-Monthly Review')
        ->from('2025-01-05')
        ->to('2025-03-31')
        ->addPeriod('11:00', '12:00')
        ->bimonthly(['days_of_month' => [5, 20], 'start_month' => 1])
        ->save();

    $quarterly = Zap::for($user)
        ->named('Quarterly Meeting')
        ->from('2025-02-15')
        ->to('2025-11-15')
        ->addPeriod('13:00', '14:00')
        ->quarterly(['day_of_month' => 15, 'start_month' => 2])
        ->save();

    $semiannually = Zap::for($user)
        ->named('Semi-Annually Meeting')
        ->from('2025-03-30')
        ->to('2025-11-15')
        ->addPeriod('13:00', '14:00')
        ->semiannually(['day_of_month' => 20, 'start_month' => 2])
        ->save();

    $annually = Zap::for($user)
        ->named('Annually Meeting')
        ->from('2025-04-05')
        ->to('2026-11-15')
        ->addPeriod('13:00', '14:00')
        ->annually(['day_of_month' => 20, 'start_month' => 2])
        ->save();

    expect($biweekly->frequency)->toBe(Frequency::BIWEEKLY);
    expect($bimonthly->frequency)->toBe(Frequency::BIMONTHLY);
    expect($quarterly->frequency)->toBe(Frequency::QUARTERLY);
    expect($semiannually->frequency)->toBe(Frequency::SEMIANNUALLY);
    expect($annually->frequency)->toBe(Frequency::ANNUALLY);
});

it('validates maximum duration rule', function () {
    $user = createUser();

    expect(function () use ($user) {
        Zap::for($user)
            ->from('2025-01-01')
            ->addPeriod('09:00', '18:00') // 9 hour period
            ->maxDuration(480) // 8 hours max
            ->save();
    })->toThrow(InvalidScheduleException::class);
});

it('handles lpad for period times', function () {
    $user = createUser();

    $schedule = Zap::for($user)
        ->from('2025-11-10')
        ->addPeriod('8:00', '10:00')
        ->save();

    $overlapping = \Zap\Models\SchedulePeriod::query()
        ->where('schedule_id', $schedule->id)
        ->overlapping('2025-11-10', '7:00', '9:00')
        ->exists();

    expect($overlapping)->toBeTrue();
});
