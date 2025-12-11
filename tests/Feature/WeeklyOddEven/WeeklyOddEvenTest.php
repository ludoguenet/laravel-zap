<?php

use Illuminate\Support\Carbon;
use Zap\Enums\ScheduleTypes;
use Zap\Exceptions\ScheduleConflictException;
use Zap\Facades\Zap;
use Zap\Helper\DateHelper;
use Zap\Models\Schedule;

describe('Weekly Odd/Even ', function () {

    it('can create an availability for even weeks', function () {
        $user = createUser();

        $availability = Zap::for($user)
            ->named('Office Hours for Even Weeks')
            ->availability()
            ->forYear(now()->addYear()->year)
            ->addPeriod('09:00', '12:00')
            ->addPeriod('14:00', '17:00')
            ->weeklyEven(['monday', 'tuesday', 'wednesday', 'friday'])
            ->save();

        expect($availability)->not->toBeNull();

        expect($availability->periods)->toHaveCount(2);

        expect($availability->periods[0]->start_time)->toBe('09:00');
        expect($availability->periods[0]->end_time)->toBe('12:00');

        expect($availability->periods[1]->start_time)->toBe('14:00');
        expect($availability->periods[1]->end_time)->toBe('17:00');

        expect($availability->frequency->value)->toBe('weekly_even');

        expect($availability->frequency_config)
            ->toBeInstanceOf(\Zap\Data\WeeklyEvenOddFrequencyConfig\WeeklyEvenFrequencyConfig::class);
        expect($availability->frequency_config->days)
            ->toBe([
                'monday',
                'tuesday',
                'wednesday',
                'friday',
            ]);

    });

    it('can create an availability for odd weeks', function () {
        $user = createUser();

        $availability = Zap::for($user)
            ->named('Office Hours for Odd Weeks')
            ->availability()
            ->forYear(now()->addYear()->year)
            ->addPeriod('09:00', '12:00')
            ->addPeriod('14:00', '17:00')
            ->weeklyOdd(['monday', 'wednesday'])
            ->save();

        expect($availability)->not->toBeNull();

        expect($availability->periods)->toHaveCount(2);

        expect($availability->periods[0]->start_time)->toBe('09:00');
        expect($availability->periods[0]->end_time)->toBe('12:00');

        expect($availability->periods[1]->start_time)->toBe('14:00');
        expect($availability->periods[1]->end_time)->toBe('17:00');

        expect($availability->frequency->value)->toBe(Frequency::WEEKLY_ODD->value);

        expect($availability->frequency_config)
            ->toBeInstanceOf(\Zap\Data\WeeklyEvenOddFrequencyConfig\WeeklyOddFrequencyConfig::class);
        expect($availability->frequency_config->days)->toBe(['monday', 'wednesday']);
    });

    test('getBookableSlots with weekly even and odd', function () {
        $user = createUser();

        Zap::for($user)
            ->named('Office Hours for Even Weeks')
            ->availability()
            ->forYear(2025)
            ->addPeriod('09:00', '12:00')
            ->addPeriod('14:00', '17:00')
            ->weeklyEven(['monday', 'tuesday', 'wednesday', 'friday'])
            ->save();

        //
        // 1. Odd week date → no slots should be generated, even if the weekday is allowed
        //
        $slotsWeekOdd = $user->getBookableSlots(
            date: '2025-01-01', // Wednesday, but week 1 (odd week) → no slots should be generated
            slotDuration: 60
        );
        expect($slotsWeekOdd)->toBeEmpty();

        //
        // 2. Unauthorized day (Thursday) → no slots should be generated
        //
        $slotsThursday = $user->getBookableSlots(
            date: '2025-01-02', // Thursday
            slotDuration: 60
        );
        expect($slotsThursday)->toBeEmpty();

        //
        // 3. Even week date and allowed day → 6 slots should be generated
        //
        $slotsWeekEven = $user->getBookableSlots(
            date: '2025-01-06', // Monday, week 2 → 6 slots should be generated
            slotDuration: 60
        );

        expect($slotsWeekEven)->toHaveCount(6);
        expect($slotsWeekEven)->toBe([
            ['start_time' => '09:00', 'end_time' => '10:00', 'is_available' => true, 'buffer_minutes' => 0],
            ['start_time' => '10:00', 'end_time' => '11:00', 'is_available' => true, 'buffer_minutes' => 0],
            ['start_time' => '11:00', 'end_time' => '12:00', 'is_available' => true, 'buffer_minutes' => 0],
            ['start_time' => '14:00', 'end_time' => '15:00', 'is_available' => true, 'buffer_minutes' => 0],
            ['start_time' => '15:00', 'end_time' => '16:00', 'is_available' => true, 'buffer_minutes' => 0],
            ['start_time' => '16:00', 'end_time' => '17:00', 'is_available' => true, 'buffer_minutes' => 0],
        ]);
    });

    test('getBookableSlots with weekly odd', function () {
        $user = createUser();

        Zap::for($user)
            ->named('Office Hours for Odd Weeks')
            ->availability()
            ->forYear(2025)
            ->addPeriod('09:00', '12:00')
            ->addPeriod('14:00', '17:00')
            ->weeklyOdd(['monday', 'wednesday', 'friday'])
            ->save();

        //
        // 1. Even week date → no slots should be generated, even if the day is allowed
        //
        $slotsWeekEven = $user->getBookableSlots(
            date: '2025-01-06', // Monday, week 2 (even)
            slotDuration: 60
        );
        expect($slotsWeekEven)->toBeEmpty();

        //
        // 2. Unauthorized day on an odd week → no slots should be generated
        //
        $slotsTuesdayOdd = $user->getBookableSlots(
            date: '2025-01-07', // Tuesday, week 1 (odd) but unauthorized → no slots should be generated
            slotDuration: 60
        );
        expect($slotsTuesdayOdd)->toBeEmpty();

        //
        // 3. Odd week date and allowed day → 6 slots should be generated
        //
        $slotsWeekOdd = $user->getBookableSlots(
            date: '2025-01-01', // Wednesday, week 1 (odd)
            slotDuration: 60
        );

        expect($slotsWeekOdd)->toHaveCount(6);

        expect($slotsWeekOdd)->toBe([
            ['start_time' => '09:00', 'end_time' => '10:00', 'is_available' => true, 'buffer_minutes' => 0],
            ['start_time' => '10:00', 'end_time' => '11:00', 'is_available' => true, 'buffer_minutes' => 0],
            ['start_time' => '11:00', 'end_time' => '12:00', 'is_available' => true, 'buffer_minutes' => 0],
            ['start_time' => '14:00', 'end_time' => '15:00', 'is_available' => true, 'buffer_minutes' => 0],
            ['start_time' => '15:00', 'end_time' => '16:00', 'is_available' => true, 'buffer_minutes' => 0],
            ['start_time' => '16:00', 'end_time' => '17:00', 'is_available' => true, 'buffer_minutes' => 0],
        ]);
    });

    test('getNextBookableSlot returns the correct next available slot for weekly even in 2025', function () {
        $user = createUser();

        // Creating an availability for even weeks in 2025
        // Periods: 09:00-12:00 and 14:00-17:00
        // Allowed days: Monday, Tuesday, Wednesday, Friday
        Zap::for($user)
            ->named('Office Hours for Even Weeks')
            ->availability()
            ->forYear(2025)
            ->addPeriod('09:00', '12:00')
            ->addPeriod('14:00', '17:00')
            ->weeklyEven(['monday', 'tuesday', 'wednesday', 'friday'])
            ->save();

        //
        // Test: initial date on an unauthorized day → the slot should move to the next allowed day
        // Thursday 2025-01-02 → week 1 (odd) → not allowed for weekly_even
        //
        $nextSlotThursday = $user->getNextBookableSlot(
            afterDate: '2025-01-02',
            duration: 60,
            bufferMinutes: 10
        );

        // The next allowed day in an even week is Monday, 2025-01-06
        expect($nextSlotThursday)->toBe([
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_available' => true,
            'buffer_minutes' => 10,
            'date' => '2025-01-06'
        ]);

        //
        // Test: initial date on an allowed day (Monday) with enough time for the duration and buffer
        //
        $nextSlotMonday = $user->getNextBookableSlot(
            afterDate: '2025-01-06', // Monday, even week
            duration: 110, // 1h50
            bufferMinutes: 10
        );

        // The slot starts at 10:00 → 11:50, still within the 09:00-12:00 period
        expect($nextSlotMonday)->toBe([
            'start_time' => '09:00',
            'end_time' => '10:50',
            'is_available' => true,
            'buffer_minutes' => 10,
            'date' => '2025-01-06'
        ]);

        //
        // Test: date too late in the day → the slot should move to the next allowed day
        //
        $nextSlotLate = $user->getNextBookableSlot(
            afterDate: '2025-01-06 16:30', // Monday, even week
            duration: 60,
            bufferMinutes: 5
        );

        // The last slot of the day is 16:00-17:00 → not enough time for 60 min with buffer
        // The next slot is therefore the first allowed slot on the next day in an even week (Tuesday, 2025-01-07)
        expect($nextSlotLate)->toBe([
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_available' => true,
            'buffer_minutes' => 5,
            'date' => '2025-01-06',
        ]);
    });

    test('getNextBookableSlot returns the correct next available slot for weekly odd in 2025', function () {
        $user = createUser();

        // Creating an availability for odd weeks in 2025
        // Periods: 09:00-12:00 and 14:00-17:00
        // Allowed days: Monday, Wednesday, Friday
        Zap::for($user)
            ->named('Office Hours for Odd Weeks')
            ->availability()
            ->forYear(2025)
            ->addPeriod('09:00', '12:00')
            ->addPeriod('14:00', '17:00')
            ->weeklyOdd(['monday', 'wednesday', 'friday'])
            ->save();

        //
        // Test: initial date on a day in an even week → no slots should be available
        // Tuesday, 2025-01-07 → week 2 (even)
        //
        $nextSlotEvenWeek = $user->getNextBookableSlot(
            afterDate: '2025-01-07',
            duration: 60,
            bufferMinutes: 10
        );

        // The next available day in an odd week is Wednesday, 2025-01-01
        expect($nextSlotEvenWeek)->toBe([
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_available' => true,
            'buffer_minutes' => 10,
            'date' => '2025-01-13',
        ]);

        //
        // Test: initial date on an allowed day (Wednesday, odd week)
        //
        $nextSlotWednesday = $user->getNextBookableSlot(
            afterDate: '2025-01-01', // mercredi semaine 1 (impair)
            duration: 110, // 1h50
            bufferMinutes: 10
        );

        // The slot starts at 09:00 → 10:50, still within the 09:00-12:00 period
        expect($nextSlotWednesday)->toBe([
            'start_time' => '09:00',
            'end_time' => '10:50',
            'is_available' => true,
            'buffer_minutes' => 10,
            'date' => '2025-01-01',
        ]);

        //
        // Test: date too late in the day → the slot should move to the next allowed day
        //
        $nextSlotLate = $user->getNextBookableSlot(
            afterDate: '2025-01-01', // mercredi semaine impaire
            duration: 60,
            bufferMinutes: 5
        );

        // The last slot of the day is 16:00-17:00 → not enough time for 60 min with buffer
        // The next slot is therefore the first allowed slot on the next day in an odd week (Friday, 2025-01-03)
        expect($nextSlotLate)->toBe([
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_available' => true,
            'buffer_minutes' => 5,
            'date' => '2025-01-01',
        ]);
    });

    it('calculates the next odd week correctly', function () {
        // Date in an odd week
        $date = Carbon::parse('2025-01-01'); // week 1 (odd)
        $nextOdd = DateHelper::nextWeekOdd($date);
        expect(DateHelper::isDateInOddIsoWeek($nextOdd))->toBeTrue();
        expect($date->diffInWeeks($nextOdd))->toBe(2.0);

        // Date in an even week
        $dateEven = Carbon::parse('2025-01-06'); // week 2 (even)
        $nextOddFromEven = DateHelper::nextWeekOdd($dateEven);
        expect(DateHelper::isDateInOddIsoWeek($nextOddFromEven))->toBeTrue();
        expect($dateEven->diffInWeeks($nextOddFromEven))->toBe(1.0);
    });

    it('calculates the next even week correctly', function () {
        // Date in an even week
        $date = Carbon::parse('2025-01-06'); // week 2 (even)
        $nextEven = DateHelper::nextWeekEven($date);
        expect(DateHelper::isDateInEvenIsoWeek($nextEven))->toBeTrue();
        expect($date->diffInWeeks($nextEven))->toBe(2.0);

        // Date in an odd week
        $dateOdd = Carbon::parse('2025-01-01'); // week 1 (odd)
        $nextEvenFromOdd = DateHelper::nextWeekEven($dateOdd);
        expect(DateHelper::isDateInEvenIsoWeek($nextEvenFromOdd))->toBeTrue();
        expect($dateOdd->diffInWeeks($nextEvenFromOdd))->toBe(1.0);
    });


});