<?php

use Zap\Facades\Zap;

it('returns null when no availability schedules exist', function () {
    $user = createUser();

    $nextSlot = $user->getNextBookableSlot('2025-01-01');

    expect($nextSlot)->toBeNull();
});

it('finds next bookable slot within availability schedules', function () {
    $user = createUser();

    // Create availability schedule
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '17:00')
        ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
        ->save();

    $nextSlot = $user->getNextBookableSlot('2025-01-01');

    expect($nextSlot)->not()->toBeNull();
    expect($nextSlot['date'])->toBe('2025-01-01');
    expect($nextSlot['start_time'])->toBe('09:00');
    expect($nextSlot['is_available'])->toBeTrue();
});

it('skips days without availability schedules', function () {
    $user = createUser();

    // Create availability only for Friday
    Zap::for($user)
        ->availability()
        ->from('2025-01-01') // Wednesday
        ->addPeriod('09:00', '17:00')
        ->weekly(['friday'])
        ->save();

    // Starting from Wednesday, should find Friday
    $nextSlot = $user->getNextBookableSlot('2025-01-01');

    expect($nextSlot)->not()->toBeNull();
    expect($nextSlot['date'])->toBe('2025-01-03'); // Friday
});

it('skips past occupied slots', function () {
    $user = createUser();

    // Create availability
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '12:00')
        ->save();

    // Create appointment for first slot
    Zap::for($user)
        ->appointment()
        ->from('2025-01-01')
        ->addPeriod('09:00', '10:00')
        ->save();

    $nextSlot = $user->getNextBookableSlot('2025-01-01');

    expect($nextSlot)->not()->toBeNull();
    expect($nextSlot['start_time'])->toBe('10:00'); // Should skip 09:00 slot
    expect($nextSlot['is_available'])->toBeTrue();
});

it('searches multiple days ahead', function () {
    $user = createUser();

    // Create availability only for day 3 days ahead
    Zap::for($user)
        ->availability()
        ->from('2025-01-04') // Saturday
        ->addPeriod('09:00', '17:00')
        ->save();

    $nextSlot = $user->getNextBookableSlot('2025-01-01');

    expect($nextSlot)->not()->toBeNull();
    expect($nextSlot['date'])->toBe('2025-01-04');
});

it('returns null when no slots found within 365 days', function () {
    $user = createUser();

    // Create availability far in the future
    Zap::for($user)
        ->availability()
        ->from('2025-02-15') // More than 30 days ahead
        ->addPeriod('09:00', '17:00')
        ->save();

    $nextSlot = $user->getNextBookableSlot('2024-01-01');

    expect($nextSlot)->toBeNull();
});

it('respects buffer time when finding next slot', function () {
    $user = createUser();

    // Create availability
    Zap::for($user)
        ->availability()
        ->from('2025-01-01')
        ->addPeriod('09:00', '10:30')
        ->save();

    // With 15-minute buffer, only one 60-minute slot should fit
    $nextSlot = $user->getNextBookableSlot('2025-01-01', 60, 15);

    expect($nextSlot)->not()->toBeNull();
    expect($nextSlot['start_time'])->toBe('09:00');
    expect($nextSlot['buffer_minutes'])->toBe(15);
});

it('validates duration parameter', function () {
    $user = createUser();

    $nextSlot = $user->getNextBookableSlot('2025-01-01', 0);

    expect($nextSlot)->toBeNull();
});

it('uses current date when afterDate is null', function () {
    $user = createUser();

    // Create availability for today
    Zap::for($user)
        ->availability()
        ->from(now()->format('Y-m-d'))
        ->addPeriod('09:00', '17:00')
        ->save();

    $nextSlot = $user->getNextBookableSlot();

    expect($nextSlot)->not()->toBeNull();
    expect($nextSlot['date'])->toBe(now()->format('Y-m-d'));
});

it('finds next slot for extended recurring frequencies', function () {
    $user = createUser();

    // Bi-weekly availability on Mondays starting Jan 6
    Zap::for($user)
        ->availability()
        ->biweekly(['monday'])
        ->from('2025-01-06')
        ->to('2025-02-28')
        ->addPeriod('09:00', '11:00')
        ->save();

    $nextSlot = $user->getNextBookableSlot('2025-01-01');

    expect($nextSlot)->not()->toBeNull();
    expect($nextSlot['date'])->toBe('2025-01-06');
    expect($nextSlot['start_time'])->toBe('09:00');
});

dataset('next_bookable_slots_recurring_cases', [
    'daily' => [[
        'make' => function ($user) {
            Zap::for($user)->availability()
                ->daily()
                ->from('2025-01-01')
                ->addPeriod('09:00', '10:00')
                ->save();
        },
        'after_dates' => ['2025-01-01', '2025-01-02'],
        'expected_dates' => ['2025-01-01', '2025-01-02'],
    ]],
    'weekly multi-day' => [[
        'make' => function ($user) {
            Zap::for($user)->availability()
                ->weekly(['wednesday', 'friday'])
                ->from('2025-01-01') // Wednesday
                ->addPeriod('09:00', '10:00')
                ->save();
        },
        'after_dates' => ['2025-01-01', '2025-01-02', '2025-01-04'],
        'expected_dates' => ['2025-01-01', '2025-01-03', '2025-01-08'],
    ]],
    'biweekly multi-day' => [[
        'make' => function ($user) {
            Zap::for($user)->availability()
                ->biweekly(['monday', 'wednesday'])
                ->from('2025-01-06') // Monday
                ->to('2025-02-28')
                ->addPeriod('09:00', '10:00')
                ->save();
        },
        'after_dates' => ['2025-01-05', '2025-01-07', '2025-01-10'],
        'expected_dates' => ['2025-01-06', '2025-01-08', '2025-01-20'],
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
        'after_dates' => ['2025-01-04', '2025-01-06', '2025-01-21'],
        'expected_dates' => ['2025-01-05', '2025-01-20', '2025-02-05'],
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
        'after_dates' => ['2025-01-04', '2025-01-06', '2025-01-21'],
        'expected_dates' => ['2025-01-05', '2025-01-20', '2025-03-05'],
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
        'after_dates' => ['2025-01-01', '2025-02-16', '2025-02-21'],
        'expected_dates' => ['2025-02-20', '2025-02-20', '2025-05-05'],
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
        'after_dates' => ['2025-01-01', '2025-03-11', '2025-03-26'],
        'expected_dates' => ['2025-03-10', '2025-03-25', '2025-09-10'],
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
        'after_dates' => ['2025-01-01', '2025-04-02', '2025-04-16'],
        'expected_dates' => ['2025-04-01', '2025-04-15', '2026-04-01'],
    ]],
]);

it('finds next slot for all supported recurring frequencies', function ($case) {
    $user = createUser();
    $case['make']($user);

    foreach ($case['after_dates'] as $index => $after) {
        $expectedDate = $case['expected_dates'][$index];
        $nextSlot = $user->getNextBookableSlot($after, 60);
        expect($nextSlot)->not()->toBeNull("Failed asserting next slot after {$after}");
        expect($nextSlot['date'])->toBe($expectedDate);
        expect($nextSlot['start_time'])->toBe('09:00');
    }
})->with('next_bookable_slots_recurring_cases');
