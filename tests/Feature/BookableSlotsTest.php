<?php

use Zap\Facades\Zap;

it('returns empty array when no availability schedules exist', function () {
    $user = createUser();

    $slots = $user->getBookableSlots('2025-01-01');

    expect($slots)->toBe([]);
});

it('returns slots only within availability schedule periods', function () {
    $user = createUser();

    // Create availability schedule (9:00-17:00)
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '17:00')
        ->save();

    $slots = $user->getBookableSlots('2025-01-01', 60);

    expect($slots)->not()->toBeEmpty();

    // All slots should be within availability window
    foreach ($slots as $slot) {
        expect($slot['start_time'])->toBeGreaterThanOrEqual('09:00');
        expect($slot['end_time'])->toBeLessThanOrEqual('17:00');
    }
});

it('respects multiple availability periods in same schedule', function () {
    $user = createUser();

    // Create availability with morning and afternoon sessions
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '12:00') // Morning
        ->addPeriod('14:00', '17:00') // Afternoon
        ->save();

    $slots = $user->getBookableSlots('2025-01-01', 60);

    $morningSlots = collect($slots)->filter(fn ($slot) => $slot['start_time'] >= '09:00' && $slot['end_time'] <= '12:00');
    $afternoonSlots = collect($slots)->filter(fn ($slot) => $slot['start_time'] >= '14:00' && $slot['end_time'] <= '17:00');
    $lunchSlots = collect($slots)->filter(fn ($slot) => $slot['start_time'] >= '12:00' && $slot['end_time'] <= '14:00');

    expect($morningSlots)->not()->toBeEmpty();
    expect($afternoonSlots)->not()->toBeEmpty();
    expect($lunchSlots)->toBeEmpty(); // No slots during lunch break
});

it('handles multiple overlapping availability schedules', function () {
    $user = createUser();

    // Create overlapping availability schedules
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '15:00')
        ->save();

    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('12:00', '18:00')
        ->save();

    $slots = $user->getBookableSlots('2025-01-01', 60);

    // Should cover 09:00-18:00 without duplicates
    expect($slots)->not()->toBeEmpty();

    $startTimes = collect($slots)->pluck('start_time')->unique();
    expect($startTimes->count())->toBe($startTimes->count()); // No duplicates

    // Check coverage
    $firstSlot = collect($slots)->first();
    $lastSlot = collect($slots)->last();
    expect($firstSlot['start_time'])->toBe('09:00');
    expect($lastSlot['start_time'])->toBe('17:00'); // Last 60-minute slot that fits
});

it('excludes slots that conflict with appointments', function () {
    $user = createUser();

    // Create availability
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '17:00')
        ->save();

    // Create appointment
    Zap::for($user)
        ->appointment()
        ->from('2025-01-01')
        ->addPeriod('10:00', '11:00')
        ->save();

    $slots = $user->getBookableSlots('2025-01-01', 60);

    // Should have slots before and after appointment, but not during
    $availableSlots = collect($slots)->where('is_available', true);
    $unavailableSlots = collect($slots)->where('is_available', false);

    expect($availableSlots)->not()->toBeEmpty();
    expect($unavailableSlots)->not()->toBeEmpty();

    // The 10:00-11:00 slot should be unavailable
    $conflictSlot = collect($slots)->first(fn ($slot) => $slot['start_time'] === '10:00');
    expect($conflictSlot['is_available'])->toBeFalse();
});

it('excludes slots that conflict with blocked periods', function () {
    $user = createUser();

    // Create availability
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '17:00')
        ->save();

    // Create blocked period
    Zap::for($user)
        ->blocked()
        ->from('2025-01-01')
        ->addPeriod('12:00', '13:00')
        ->save();

    $slots = $user->getBookableSlots('2025-01-01', 60);

    // The 12:00-13:00 slot should be unavailable
    $blockedSlot = collect($slots)->first(fn ($slot) => $slot['start_time'] === '12:00');
    expect($blockedSlot['is_available'])->toBeFalse();
});

it('works with recurring availability schedules', function () {
    $user = createUser();

    // Create weekly recurring availability
    Zap::for($user)
        ->availability()
        ->from('2025-01-01') // Wednesday
        ->addPeriod('09:00', '17:00')
        ->weekly(['wednesday', 'friday'])
        ->save();

    // Wednesday should have slots
    $wednesdaySlots = $user->getBookableSlots('2025-01-01', 60);
    expect($wednesdaySlots)->not()->toBeEmpty();

    // Thursday should have no slots
    $thursdaySlots = $user->getBookableSlots('2025-01-02', 60);
    expect($thursdaySlots)->toBeEmpty();

    // Friday should have slots
    $fridaySlots = $user->getBookableSlots('2025-01-03', 60);
    expect($fridaySlots)->not()->toBeEmpty();
});

it('respects buffer time configuration', function () {
    $user = createUser();

    // Create availability
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '12:00')
        ->save();

    // Test with 15-minute buffer
    $slots = $user->getBookableSlots('2025-01-01', 60, 15);

    expect($slots)->not()->toBeEmpty();

    // Slots should be spaced 75 minutes apart (60 + 15 buffer)
    if (count($slots) > 1) {
        $firstSlot = $slots[0];
        $secondSlot = $slots[1];

        $time1 = \Carbon\Carbon::parse('2025-01-01 '.$firstSlot['start_time']);
        $time2 = \Carbon\Carbon::parse('2025-01-01 '.$secondSlot['start_time']);

        expect((int) $time1->diffInMinutes($time2))->toBe(75);
    } else {
        $this->markTestIncomplete('Not enough slots generated to test buffer spacing.');
    }
});

it('handles inactive availability schedules', function () {
    $user = createUser();

    // Create inactive availability schedule
    $schedule = Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '17:00')
        ->save();

    // Deactivate the schedule
    $schedule->update(['is_active' => false]);

    $slots = $user->getBookableSlots('2025-01-01', 60);

    expect($slots)->toBeEmpty();
});

it('handles availability schedules outside date range', function () {
    $user = createUser();

    // Create availability for different dates
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->to('2025-01-02')
        ->addPeriod('09:00', '17:00')
        ->save();

    // Request slots for date outside range
    $slots = $user->getBookableSlots('2025-01-03', 60);

    expect($slots)->toBeEmpty();
});

it('supports extended recurring frequencies when building bookable slots', function () {
    $user = createUser();

    // Bi-weekly availability on Mondays starting Jan 6 (skip following week)
    Zap::for($user)
        ->availability()
        ->biweekly(['monday'])
        ->from('2025-01-06')
        ->to('2025-02-28')
        ->addPeriod('09:00', '11:00')
        ->save();

    // Bi-monthly availability on the 5th and 20th
    Zap::for($user)
        ->availability()
        ->bimonthly(['days_of_month' => [5, 20]])
        ->from('2025-01-05')
        ->to('2025-03-31')
        ->addPeriod('13:00', '15:00')
        ->save();

    $biweeklySlotsAnchor = $user->getBookableSlots('2025-01-06', 60);
    $biweeklySlotsSkipWeek = $user->getBookableSlots('2025-01-13', 60);
    $biweeklySlotsNext = $user->getBookableSlots('2025-01-20', 60);

    expect($biweeklySlotsAnchor)->not()->toBeEmpty();
    expect($biweeklySlotsSkipWeek)->toBeEmpty();
    expect($biweeklySlotsNext)->not()->toBeEmpty();

    $bimonthlySlotsDay5 = $user->getBookableSlots('2025-01-05', 60);
    $bimonthlySlotsDay10 = $user->getBookableSlots('2025-01-10', 60);
    $bimonthlySlotsDay20 = $user->getBookableSlots('2025-01-20', 60);

    expect($bimonthlySlotsDay5)->not()->toBeEmpty();
    expect($bimonthlySlotsDay10)->toBeEmpty();
    expect($bimonthlySlotsDay20)->not()->toBeEmpty();
});

dataset('bookable_slots_recurring_cases', [
    'daily' => [[
        'make' => function ($user) {
            Zap::for($user)->availability()
                ->daily()
                ->from('2025-01-01')
                ->addPeriod('09:00', '10:00')
                ->save();
        },
        'expected_dates' => ['2025-01-01', '2025-01-02'],
        'off_dates' => ['2024-12-31'],
    ]],
    'weekly multi-day' => [[
        'make' => function ($user) {
            Zap::for($user)->availability()
                ->weekly(['wednesday', 'friday'])
                ->from('2025-01-01') // Wednesday
                ->addPeriod('09:00', '10:00')
                ->save();
        },
        'expected_dates' => ['2025-01-01', '2025-01-03', '2025-01-08'],
        'off_dates' => ['2025-01-02'],
    ]],
    'biweekly multi-day' => [[
        'make' => function ($user) {
            Zap::for($user)->availability()
                ->biweekly(['monday', 'wednesday'])
                ->from('2025-01-06')
                ->to('2025-02-28')
                ->addPeriod('09:00', '10:00')
                ->save();
        },
        'expected_dates' => ['2025-01-06', '2025-01-08', '2025-01-20', '2025-01-22'],
        'off_dates' => ['2025-01-13', '2025-01-15', '2025-01-27', '2025-01-29'],
    ]],
    'monthly multi-day' => [[
        'make' => function ($user) {
            Zap::for($user)->availability()
                ->monthly(['days_of_month' => [5, 20]])
                ->from('2025-01-05')
                ->to('2025-06-30')
                ->addPeriod('09:00', '10:00')
                ->save();
        },
        'expected_dates' => ['2025-01-05', '2025-01-20', '2025-02-05'],
        'off_dates' => ['2025-01-06'],
    ]],
    'bimonthly multi-day' => [[
        'make' => function ($user) {
            Zap::for($user)->availability()
                ->bimonthly(['days_of_month' => [5, 20], 'start_month' => 1])
                ->from('2025-01-05')
                ->to('2025-06-30')
                ->addPeriod('09:00', '10:00')
                ->save();
        },
        'expected_dates' => ['2025-01-05', '2025-01-20', '2025-03-05', '2025-03-20'],
        'off_dates' => ['2025-01-10', '2025-02-05', '2025-02-20'],
    ]],
    'quarterly multi-day' => [[
        'make' => function ($user) {
            Zap::for($user)->availability()
                ->quarterly(['days_of_month' => [5, 20], 'start_month' => 2])
                ->from('2025-02-15')
                ->to('2025-11-15')
                ->addPeriod('09:00', '10:00')
                ->save();
        },
        'expected_dates' => ['2025-02-20', '2025-05-05', '2025-05-20'],
        'off_dates' => ['2025-03-15'],
    ]],
    'semiannual multi-day' => [[
        'make' => function ($user) {
            Zap::for($user)->availability()
                ->semiannually(['days_of_month' => [10, 25], 'start_month' => 3])
                ->from('2025-03-10')
                ->to('2025-12-10')
                ->addPeriod('09:00', '10:00')
                ->save();
        },
        'expected_dates' => ['2025-03-10', '2025-03-25', '2025-09-10'],
        'off_dates' => ['2025-04-10'],
    ]],
    'annual multi-day' => [[
        'make' => function ($user) {
            Zap::for($user)->availability()
                ->annually(['days_of_month' => [1, 15], 'start_month' => 4])
                ->from('2025-04-01')
                ->to('2026-04-01')
                ->addPeriod('09:00', '10:00')
                ->save();
        },
        'expected_dates' => ['2025-04-01', '2025-04-15', '2026-04-01'],
        'off_dates' => ['2025-04-02'],
    ]],
]);

it('builds slots for all supported recurring frequencies', function ($case) {
    $user = createUser();
    $case['make']($user);

    foreach ($case['expected_dates'] as $expectedDate) {
        $expectedSlots = $user->getBookableSlots($expectedDate, 60);
        expect($expectedSlots)->not()->toBeEmpty("Failed asserting slots on {$expectedDate}");
    }

    foreach ($case['off_dates'] as $offDate) {
        $offSlots = $user->getBookableSlots($offDate, 60);
        expect($offSlots)->toBeEmpty("Failed asserting no slots on {$offDate}");
    }
})->with('bookable_slots_recurring_cases');
