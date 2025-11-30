<?php

use Carbon\Carbon;
use Zap\Facades\Zap;

describe('Comprehensive Slots Feature Tests', function () {

    beforeEach(function () {
        Carbon::setTestNow('2025-03-14 08:00:00'); // Friday
    });

    afterEach(function () {
        Carbon::setTestNow(); // Reset
    });

    describe('getBookableSlots', function () {

        it('handles different slot durations correctly', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('09:00', '13:00')
                ->save();

            // Block 10:00-12:00
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('10:00', '12:00')
                ->save();

            // Test 15-minute slots
            $slots15 = $user->getBookableSlots('2025-03-15', 15);
            expect($slots15)->toHaveCount(16); // 4 hours = 16 slots of 15 minutes

            // Check that 10:00-12:00 slots are blocked
            $blockedSlots15 = array_filter(
                $slots15,
                fn ($slot) => $slot['start_time'] >= '10:00' && $slot['start_time'] < '12:00'
            );
            foreach ($blockedSlots15 as $slot) {
                expect($slot['is_available'])->toBeFalse(
                    "15-min slot {$slot['start_time']}-{$slot['end_time']} should be blocked"
                );
            }

            // Test 30-minute slots
            $slots30 = $user->getBookableSlots('2025-03-15', 30);
            expect($slots30)->toHaveCount(8); // 4 hours = 8 slots of 30 minutes

            // Test 60-minute slots
            $slots60 = $user->getBookableSlots('2025-03-15', 60);
            expect($slots60)->toHaveCount(4); // 4 hours = 4 slots of 60 minutes

            // Test 120-minute slots
            $slots120 = $user->getBookableSlots('2025-03-15', 120);
            expect($slots120)->toHaveCount(2); // 4 hours = 2 slots of 120 minutes
        });

        it('handles slots that span across schedule boundaries', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('10:00', '12:00')
                ->save();

            // Block 10:30-11:30 (creates a gap in the middle)
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('10:30', '11:30')
                ->save();

            $slots = $user->getBookableSlots('2025-03-15', 60);

            // 10:00-11:00 should overlap with blocked time, so unavailable
            $slot10 = collect($slots)->firstWhere('start_time', '10:00');
            expect($slot10['is_available'])->toBeFalse();

            // 11:00-12:00 should also overlap with blocked time, so unavailable
            $slot11 = collect($slots)->firstWhere('start_time', '11:00');
            expect($slot11['is_available'])->toBeFalse();
        });

        it('handles multiple periods in the same day', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('08:00', '16:00')
                ->save();

            // Create schedule with multiple periods
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('09:00', '10:00') // Morning block
                ->addPeriod('14:00', '15:00') // Afternoon block
                ->save();

            $slots = $user->getBookableSlots('2025-03-15', 60);

            // Check each slot
            $expected = [
                '08:00' => true,  // Available
                '09:00' => false, // Blocked by morning period
                '10:00' => true,  // Available
                '11:00' => true,  // Available
                '12:00' => true,  // Available
                '13:00' => true,  // Available
                '14:00' => false, // Blocked by afternoon period
                '15:00' => true,  // Available
            ];

            foreach ($slots as $slot) {
                $startTime = $slot['start_time'];
                if (isset($expected[$startTime])) {
                    expect($slot['is_available'])->toBe(
                        $expected[$startTime],
                        "Slot {$startTime} availability mismatch"
                    );
                }
            }
        });

        it('handles empty days with no availability schedules', function () {
            $user = createUser();

            // No availability schedules created
            $slots = $user->getBookableSlots('2025-03-15', 60);

            expect($slots)->toBeEmpty();
        });

        it('handles availability schedules with different time ranges', function () {
            $user = createUser();

            // Create availability for morning
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('06:00', '10:00')
                ->save();

            // Create availability for evening
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('18:00', '22:00')
                ->save();

            // Create availability around blocked time
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('11:00', '14:00')
                ->save();

            // Block middle of the day
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('12:00', '13:00')
                ->save();

            // Test early morning hours
            $morningSlots = $user->getBookableSlots('2025-03-15', 60);
            $morningFiltered = collect($morningSlots)->filter(fn ($slot) => $slot['start_time'] >= '06:00' && $slot['start_time'] < '10:00');
            expect($morningFiltered)->not()->toBeEmpty();
            foreach ($morningFiltered as $slot) {
                expect($slot['is_available'])->toBeTrue(
                    "Morning slot {$slot['start_time']} should be available"
                );
            }

            // Test late evening hours
            $eveningFiltered = collect($morningSlots)->filter(fn ($slot) => $slot['start_time'] >= '18:00' && $slot['start_time'] < '22:00');
            expect($eveningFiltered)->not()->toBeEmpty();
            foreach ($eveningFiltered as $slot) {
                expect($slot['is_available'])->toBeTrue(
                    "Evening slot {$slot['start_time']} should be available"
                );
            }

            // Test around the blocked time
            $midDayFiltered = collect($morningSlots)->filter(fn ($slot) => $slot['start_time'] >= '11:00' && $slot['start_time'] < '14:00');
            $slot12 = collect($midDayFiltered)->firstWhere('start_time', '12:00');
            expect($slot12['is_available'])->toBeFalse(); // 12:00-13:00 (blocked)
        });

        it('handles slots that do not fit evenly into availability period', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('09:00', '13:00')
                ->save();

            // Test 90-minute slots in a 4-hour window
            $slots = $user->getBookableSlots('2025-03-15', 90);

            // Should create slots at: 09:00-10:30, 10:30-12:00
            // 12:00-13:30 extends beyond end time, so should be excluded
            expect($slots)->toHaveCount(2);
            expect($slots[0]['start_time'])->toBe('09:00');
            expect($slots[0]['end_time'])->toBe('10:30');
            expect($slots[1]['start_time'])->toBe('10:30');
            expect($slots[1]['end_time'])->toBe('12:00');
        });

        it('handles recurring daily schedules with gaps', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('09:00', '17:00')
                ->daily()
                ->save();

            // Block every day 10:00-11:00 and 15:00-16:00
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('10:00', '11:00')
                ->addPeriod('15:00', '16:00')
                ->daily()
                ->save();

            $slots = $user->getBookableSlots('2025-03-15', 60);

            $expected = [
                '09:00' => true,  // Available
                '10:00' => false, // Blocked
                '11:00' => true,  // Available
                '12:00' => true,  // Available
                '13:00' => true,  // Available
                '14:00' => true,  // Available
                '15:00' => false, // Blocked
                '16:00' => true,  // Available
            ];

            foreach ($slots as $slot) {
                $startTime = $slot['start_time'];
                if (isset($expected[$startTime])) {
                    expect($slot['is_available'])->toBe(
                        $expected[$startTime],
                        "Slot {$startTime} availability mismatch in recurring schedule"
                    );
                }
            }
        });

        it('handles overlapping recurring schedules', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-17')
                ->addPeriod('08:00', '13:00')
                ->weekly(['monday', 'tuesday'])
                ->save();

            // Create blocked schedule that blocks 9-11 on Mondays
            Zap::for($user)
                ->blocked()
                ->from('2025-03-17') // Monday
                ->addPeriod('09:00', '11:00')
                ->weekly(['monday'])
                ->save();

            // Create blocked schedule that blocks 10-12 on Tuesdays (different day to avoid conflict)
            Zap::for($user)
                ->blocked()
                ->from('2025-03-18') // Tuesday
                ->addPeriod('10:00', '12:00')
                ->weekly(['tuesday'])
                ->save();

            // Test Monday - should have first schedule blocking 9-11
            $mondaySlots = $user->getBookableSlots('2025-03-17', 60);
            $monday08 = collect($mondaySlots)->firstWhere('start_time', '08:00');
            $monday09 = collect($mondaySlots)->firstWhere('start_time', '09:00');
            $monday10 = collect($mondaySlots)->firstWhere('start_time', '10:00');
            $monday11 = collect($mondaySlots)->firstWhere('start_time', '11:00');
            $monday12 = collect($mondaySlots)->firstWhere('start_time', '12:00');
            expect($monday08['is_available'])->toBeTrue();  // 08:00-09:00
            expect($monday09['is_available'])->toBeFalse(); // 09:00-10:00 (first schedule)
            expect($monday10['is_available'])->toBeFalse(); // 10:00-11:00 (first schedule)
            expect($monday11['is_available'])->toBeTrue();  // 11:00-12:00
            expect($monday12['is_available'])->toBeTrue();  // 12:00-13:00

            // Test Tuesday - should have second schedule blocking 10-12
            $tuesdaySlots = $user->getBookableSlots('2025-03-18', 60);
            $tuesday08 = collect($tuesdaySlots)->firstWhere('start_time', '08:00');
            $tuesday09 = collect($tuesdaySlots)->firstWhere('start_time', '09:00');
            $tuesday10 = collect($tuesdaySlots)->firstWhere('start_time', '10:00');
            $tuesday11 = collect($tuesdaySlots)->firstWhere('start_time', '11:00');
            $tuesday12 = collect($tuesdaySlots)->firstWhere('start_time', '12:00');
            expect($tuesday08['is_available'])->toBeTrue();  // 08:00-09:00
            expect($tuesday09['is_available'])->toBeTrue();  // 09:00-10:00
            expect($tuesday10['is_available'])->toBeFalse(); // 10:00-11:00 (second schedule)
            expect($tuesday11['is_available'])->toBeFalse(); // 11:00-12:00 (second schedule)
            expect($tuesday12['is_available'])->toBeTrue();  // 12:00-13:00
        });

    });

    describe('getNextBookableSlot', function () {

        it('finds next bookable slot when current day is fully booked', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('09:00', '17:00')
                ->daily()
                ->save();

            // Block entire Saturday
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('00:00', '23:59')
                ->save();

            $nextSlot = $user->getNextBookableSlot('2025-03-15', 60);

            expect($nextSlot)->not->toBeNull();
            expect($nextSlot['date'])->toBe('2025-03-16'); // Sunday
            expect($nextSlot['start_time'])->toBe('09:00');
            expect($nextSlot['end_time'])->toBe('10:00');
        });

        it('finds slots with different duration requirements', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('09:00', '17:00')
                ->save();

            // Block 09:00-10:30 (leaves 30-minute gap before 11:00)
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('09:00', '10:30')
                ->save();

            // Looking for 30-minute slot should find 10:30-11:00
            $slot30 = $user->getNextBookableSlot('2025-03-15', 30);
            expect($slot30['start_time'])->toBe('10:30');
            expect($slot30['end_time'])->toBe('11:00');

            // Looking for 60-minute slot should find 11:00-12:00
            $slot60 = $user->getNextBookableSlot('2025-03-15', 60);
            expect($slot60['start_time'])->toBe('11:00');
            expect($slot60['end_time'])->toBe('12:00');

            // Looking for 120-minute slot should find 11:00-13:00
            $slot120 = $user->getNextBookableSlot('2025-03-15', 120);
            expect($slot120['start_time'])->toBe('11:00');
            expect($slot120['end_time'])->toBe('13:00');
        });

        it('returns null when no slots available within search window', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->to('2025-04-15')
                ->addPeriod('09:00', '17:00')
                ->daily()
                ->save();

            // Block every day for the next 30 days
            Zap::for($user)
                ->from('2025-03-15')
                ->to('2025-04-15')
                ->addPeriod('09:00', '17:00')
                ->daily()
                ->save();

            $nextSlot = $user->getNextBookableSlot('2025-03-15', 60);

            expect($nextSlot)->toBeNull();
        });

        it('finds slots that exactly fit available time', function () {
            $user = createUser();

            // Create availability schedule (recurring daily so it can find slots on other days)
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->to('2025-03-20')
                ->addPeriod('09:00', '17:00')
                ->daily()
                ->save();

            // Block 09:00-11:00 and 13:00-17:00, leaving exactly 2 hours free (11:00-13:00)
            Zap::for($user)
                ->from('2025-03-15')
                ->to('2025-03-20')
                ->addPeriod('09:00', '11:00')
                ->addPeriod('13:00', '17:00')
                ->daily()
                ->save();

            // Looking for exactly 120 minutes should find the 2-hour gap
            $slot120 = $user->getNextBookableSlot('2025-03-15', 120);
            expect($slot120)->not->toBeNull();
            expect($slot120['start_time'])->toBe('11:00');
            expect($slot120['end_time'])->toBe('13:00');

            // Looking for 121 minutes should not find a slot on the same day (gap is only 120 minutes)
            // but should find a slot on a different day if available
            $slot121 = $user->getNextBookableSlot('2025-03-15', 121);
            // Since all days have the same blocking pattern, it should return null (no slot fits)
            // OR if there's a day with more availability, it would find that
            // For this test, we expect it to search but not find a 121-minute slot
            expect($slot121)->toBeNull();
        });

        it('handles complex weekly recurring patterns', function () {
            $user = createUser();

            // Create availability only on weekends
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('09:00', '17:00')
                ->weekly(['saturday', 'sunday'])
                ->save();

            // Block Monday-Friday 9-17
            Zap::for($user)
                ->from('2025-03-17') // Monday
                ->addPeriod('09:00', '17:00')
                ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
                ->save();

            // Starting search on Monday, should find weekend
            $nextSlot = $user->getNextBookableSlot('2025-03-17', 60);

            expect($nextSlot)->not->toBeNull();
            $slotDate = Carbon::parse($nextSlot['date']);
            expect($slotDate->isWeekend())->toBeTrue();
        });

        it('handles search starting from different dates', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('09:00', '17:00')
                ->daily()
                ->save();

            // Block morning 09:00-12:00
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('09:00', '12:00')
                ->save();

            // Search starting from the day should find afternoon
            $morningSearch = $user->getNextBookableSlot('2025-03-15', 60);
            expect($morningSearch['start_time'])->toBe('12:00');
        });

        it('handles edge case at end of availability period', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('09:00', '17:00')
                ->daily()
                ->save();

            // Block most of the day, leaving only last hour
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('09:00', '16:00')
                ->save();

            // Should find the last available hour
            $nextSlot = $user->getNextBookableSlot('2025-03-15', 60);
            expect($nextSlot['start_time'])->toBe('16:00');
            expect($nextSlot['end_time'])->toBe('17:00');

            // If we need more time than available, should go to next day
            $nextSlot2h = $user->getNextBookableSlot('2025-03-15', 120);
            expect($nextSlot2h['date'])->toBe('2025-03-16');
        });

        it('handles monthly recurring schedules correctly', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-04-01')
                ->addPeriod('09:00', '17:00')
                ->daily()
                ->save();

            // Block first day of every month
            Zap::for($user)
                ->from('2025-04-01')
                ->addPeriod('09:00', '17:00')
                ->monthly(['day_of_month' => 1])
                ->save();

            // Search starting April 1st should find April 2nd
            $nextSlot = $user->getNextBookableSlot('2025-04-01', 60);
            expect($nextSlot['date'])->toBe('2025-04-02');

            // Block April 30th and May 1st to force search to May 2nd
            Zap::for($user)
                ->from('2025-04-30')
                ->addPeriod('09:00', '17:00')
                ->save();

            // Now search starting April 30th should find May 2nd (skipping blocked April 30th and May 1st)
            $nextSlot2 = $user->getNextBookableSlot('2025-04-30', 60);
            expect($nextSlot2['date'])->toBe('2025-05-02');
        });

    });

    describe('Performance and Integration Tests', function () {

        it('handles multiple overlapping schedules efficiently', function () {
            $user = createUser();

            // Create multiple overlapping schedules
            Zap::for($user)
                ->named('Daily Morning')
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('09:00', '11:00')
                ->daily()
                ->save();

            Zap::for($user)
                ->named('Weekly Afternoon')
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('14:00', '16:00')
                ->weekly(['saturday', 'sunday'])
                ->save();

            Zap::for($user)
                ->named('Monthly All Day')
                ->availability()
                ->from('2025-04-01')
                ->addPeriod('00:00', '23:59')
                ->monthly(['day_of_month' => 1])
                ->save();

            // This should execute quickly even with multiple schedules
            $startTime = microtime(true);

            $slots = $user->getBookableSlots('2025-03-15', 60);
            $nextSlot = $user->getNextBookableSlot('2025-03-15', 120);

            $executionTime = microtime(true) - $startTime;

            expect($executionTime)->toBeLessThan(0.1); // Should complete in under 100ms
            expect($slots)->toBeArray();
            expect($nextSlot)->toBeArray();
        });

        it('handles boundary conditions correctly', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('09:00', '10:00')
                ->save();

            // Schedule that ends exactly when we start looking
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('08:00', '09:00')
                ->save();

            $slots = $user->getBookableSlots('2025-03-15', 60);
            $slot09 = collect($slots)->firstWhere('start_time', '09:00');
            expect($slot09['is_available'])->toBeTrue();

            // Schedule that starts exactly when we stop looking
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('10:00', '11:00')
                ->save();

            $slots2 = $user->getBookableSlots('2025-03-15', 60);
            $slot09_2 = collect($slots2)->firstWhere('start_time', '09:00');
            expect($slot09_2['is_available'])->toBeTrue();
        });

        it('validates slot data structure', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('09:00', '12:00')
                ->save();

            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('10:00', '11:00')
                ->save();

            $slots = $user->getBookableSlots('2025-03-15', 60);

            foreach ($slots as $slot) {
                expect($slot)->toHaveKey('start_time');
                expect($slot)->toHaveKey('end_time');
                expect($slot)->toHaveKey('is_available');

                expect($slot['start_time'])->toMatch('/^\d{2}:\d{2}$/');
                expect($slot['end_time'])->toMatch('/^\d{2}:\d{2}$/');
                expect($slot['is_available'])->toBeIn([true, false]);
            }

            $nextSlot = $user->getNextBookableSlot('2025-03-15', 60);

            if ($nextSlot) {
                expect($nextSlot)->toHaveKey('date');
                expect($nextSlot)->toHaveKey('start_time');
                expect($nextSlot)->toHaveKey('end_time');
                expect($nextSlot)->toHaveKey('is_available');

                expect($nextSlot['date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
                expect($nextSlot['start_time'])->toMatch('/^\d{2}:\d{2}$/');
                expect($nextSlot['end_time'])->toMatch('/^\d{2}:\d{2}$/');
                expect($nextSlot['is_available'])->toBeTrue();
            }
        });

    });

});
