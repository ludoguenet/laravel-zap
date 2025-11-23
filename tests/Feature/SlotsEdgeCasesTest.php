<?php

use Carbon\Carbon;
use Zap\Facades\Zap;

describe('Slots Feature Edge Cases', function () {

    beforeEach(function () {
        Carbon::setTestNow('2025-03-14 08:00:00'); // Friday
    });

    afterEach(function () {
        Carbon::setTestNow(); // Reset
    });

    describe('Cross-midnight scenarios', function () {

        it('handles schedules that cross midnight', function () {
            $user = createUser();

            // Create availability schedules
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('21:00', '23:59')
                ->save();

            Zap::for($user)
                ->availability()
                ->from('2025-03-16')
                ->addPeriod('00:00', '03:00')
                ->save();

            // Schedule from 22:00 to 23:59 on Saturday
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('22:00', '23:59')
                ->save();

            // Schedule from 00:00 to 02:00 on Sunday
            Zap::for($user)
                ->from('2025-03-16')
                ->addPeriod('00:00', '02:00')
                ->save();

            // Check evening slots on Saturday
            $eveningSlots = $user->getBookableSlots('2025-03-15', 60);
            $slot22 = collect($eveningSlots)->firstWhere('start_time', '22:00');
            expect($slot22['is_available'])->toBeFalse(); // 22:00-23:00 blocked

            // Check early morning slots on Sunday
            $morningSlots = $user->getBookableSlots('2025-03-16', 60);
            $slot00 = collect($morningSlots)->firstWhere('start_time', '00:00');
            $slot01 = collect($morningSlots)->firstWhere('start_time', '01:00');
            $slot02 = collect($morningSlots)->firstWhere('start_time', '02:00');
            expect($slot00['is_available'])->toBeFalse(); // 00:00-01:00 blocked
            expect($slot01['is_available'])->toBeFalse(); // 01:00-02:00 blocked
            expect($slot02['is_available'])->toBeTrue();  // 02:00-03:00 available
        });

    });

    describe('Stress testing', function () {

        it('handles many small slots efficiently', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('00:00', '23:59')
                ->save();

            // Block random hours throughout the day
            for ($i = 10; $i < 17; $i += 2) {
                Zap::for($user)
                    ->from('2025-03-15')
                    ->addPeriod(sprintf('%02d:00', $i), sprintf('%02d:30', $i))
                    ->save();
            }

            $startTime = microtime(true);

            // Get 15-minute slots for entire day (should create 96 slots)
            $slots = $user->getBookableSlots('2025-03-15', 15);

            $executionTime = microtime(true) - $startTime;

            expect(count($slots))->toBeGreaterThan(90); // Should have many slots
            expect($executionTime)->toBeLessThan(0.5); // Should complete quickly

            // Verify some blocked slots
            $blockedSlots = array_filter($slots, fn ($slot) => ! $slot['is_available']);
            expect(count($blockedSlots))->toBeGreaterThan(5); // Should have some blocked slots
        });

        it('handles long duration searches efficiently', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('09:00', '19:00')
                ->save();

            // Block only small portions, leaving enough space for longer slots
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('10:00', '10:30')
                ->save();

            $startTime = microtime(true);

            // Look for 2-hour slot (reasonable duration that should be found)
            $nextSlot = $user->getNextBookableSlot('2025-03-15', 120);

            $executionTime = microtime(true) - $startTime;

            expect($nextSlot)->not->toBeNull();
            expect($executionTime)->toBeLessThan(1.0); // Should complete in reasonable time
        });

    });

    describe('Invalid input handling', function () {

        it('handles invalid slot durations gracefully', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('09:00', '17:00')
                ->save();

            // Invalid slot duration
            $slots2 = $user->getBookableSlots('2025-03-15', 0);
            expect($slots2)->toBeArray();
            expect(count($slots2))->toBe(0); // Should return empty array

            // Negative slot duration
            $slots3 = $user->getBookableSlots('2025-03-15', -60);
            expect($slots3)->toBeArray();
            expect(count($slots3))->toBe(0); // Should return empty array
        });

        it('handles invalid dates gracefully', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2020-01-01')
                ->addPeriod('09:00', '17:00')
                ->save();

            // Past dates
            $slots = $user->getBookableSlots('2020-01-01', 60);
            expect($slots)->toBeArray(); // Should not crash

            // Invalid date format (should not crash, but may return empty)
            try {
                $slots2 = $user->getBookableSlots('invalid-date', 60);
                expect($slots2)->toBeArray();
            } catch (Exception $e) {
                // Expected behavior - invalid date should throw exception
                expect($e)->toBeInstanceOf(Exception::class);
            }
        });

    });

    describe('Timezone considerations', function () {

        it('handles consistent timezone behavior', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('13:00', '16:00')
                ->save();

            // Schedule at specific time
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('14:00', '15:00')
                ->save();

            // Get slots in same timezone
            $slots = $user->getBookableSlots('2025-03-15', 60);

            $slot13 = collect($slots)->firstWhere('start_time', '13:00');
            $slot14 = collect($slots)->firstWhere('start_time', '14:00');
            $slot15 = collect($slots)->firstWhere('start_time', '15:00');
            expect($slot13['is_available'])->toBeTrue();  // 13:00-14:00 available
            expect($slot14['is_available'])->toBeFalse(); // 14:00-15:00 blocked
            expect($slot15['is_available'])->toBeTrue();  // 15:00-16:00 available
        });

    });

    describe('Complex recurring patterns', function () {

        it('handles multiple overlapping weekly patterns', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-17')
                ->addPeriod('08:00', '18:00')
                ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
                ->save();

            // Monday/Wednesday/Friday morning
            Zap::for($user)
                ->from('2025-03-17') // Monday
                ->addPeriod('09:00', '12:00')
                ->weekly(['monday', 'wednesday', 'friday'])
                ->save();

            // Tuesday/Thursday afternoon
            Zap::for($user)
                ->from('2025-03-18') // Tuesday
                ->addPeriod('13:00', '17:00')
                ->weekly(['tuesday', 'thursday'])
                ->save();

            // Test Monday (should block morning)
            $mondaySlots = $user->getBookableSlots('2025-03-17', 60);
            $monday09 = collect($mondaySlots)->firstWhere('start_time', '09:00');
            $monday13 = collect($mondaySlots)->firstWhere('start_time', '13:00');
            expect($monday09['is_available'])->toBeFalse(); // 09:00-10:00 blocked
            expect($monday13['is_available'])->toBeTrue();  // 13:00-14:00 available

            // Test Tuesday (should block afternoon)
            $tuesdaySlots = $user->getBookableSlots('2025-03-18', 60);
            $tuesday09 = collect($tuesdaySlots)->firstWhere('start_time', '09:00');
            $tuesday13 = collect($tuesdaySlots)->firstWhere('start_time', '13:00');
            expect($tuesday09['is_available'])->toBeTrue();  // 09:00-10:00 available
            expect($tuesday13['is_available'])->toBeFalse(); // 13:00-14:00 blocked
        });

        it('handles bi-weekly patterns', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('09:00', '17:00')
                ->weekly(['saturday'])
                ->save();

            // Every other Saturday
            Zap::for($user)
                ->from('2025-03-15') // First Saturday
                ->addPeriod('10:00', '16:00')
                ->weekly(['saturday'], 2) // Every 2 weeks
                ->save();

            // March 15 (first Saturday) - should be blocked
            $firstSaturday = $user->getBookableSlots('2025-03-15', 60);
            $blockedSlots = array_filter($firstSaturday, fn ($slot) => ! $slot['is_available']);
            expect(count($blockedSlots))->toBeGreaterThan(0);

            // March 22 (second Saturday) - should be available (bi-weekly means every 2 weeks)
            $secondSaturday = $user->getBookableSlots('2025-03-22', 60);
            $availableSlots = array_filter($secondSaturday, fn ($slot) => $slot['is_available']);
            expect(count($availableSlots))->toBeGreaterThan(0); // Should have some available slots

            // March 29 (third Saturday, which is week 2 of cycle) - should be blocked
            $thirdSaturday = $user->getBookableSlots('2025-03-29', 60);
            $blockedSlots3 = array_filter($thirdSaturday, fn ($slot) => ! $slot['is_available']);
            expect(count($blockedSlots3))->toBeGreaterThan(0);
        });

    });

    describe('Business logic edge cases', function () {

        it('handles very short slots correctly', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('10:00', '10:20')
                ->save();

            // Block 10:05-10:15 (10-minute block)
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('10:05', '10:15')
                ->save();

            // 5-minute slots should detect the conflict
            $slots5min = $user->getBookableSlots('2025-03-15', 5);

            // Should have slots: 10:00-05, 10:05-10, 10:10-15, 10:15-20
            expect(count($slots5min))->toBe(4);
            expect($slots5min[0]['is_available'])->toBeTrue();  // 10:00-10:05 available
            expect($slots5min[1]['is_available'])->toBeFalse(); // 10:05-10:10 blocked
            expect($slots5min[2]['is_available'])->toBeFalse(); // 10:10-10:15 blocked
            expect($slots5min[3]['is_available'])->toBeTrue();  // 10:15-10:20 available
        });

        it('handles exact time boundary matches', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('09:00', '12:00')
                ->save();

            // Schedule exactly 10:00-11:00
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('10:00', '11:00')
                ->save();

            // Request slot exactly 10:00-11:00
            $slots = $user->getBookableSlots('2025-03-15', 60);
            $slot10 = collect($slots)->firstWhere('start_time', '10:00');
            expect($slot10['start_time'])->toBe('10:00');
            expect($slot10['end_time'])->toBe('11:00');
            expect($slot10['is_available'])->toBeFalse(); // Should be blocked

            // Request slots 09:00-10:00 and 11:00-12:00 (adjacent)
            $slot09 = collect($slots)->firstWhere('start_time', '09:00');
            expect($slot09['is_available'])->toBeTrue(); // Should be available

            $slot11 = collect($slots)->firstWhere('start_time', '11:00');
            expect($slot11['is_available'])->toBeTrue(); // Should be available
        });

        it('finds gaps between adjacent schedules', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-15')
                ->addPeriod('08:00', '12:00')
                ->save();

            // Two adjacent schedules with gap
            Zap::for($user)
                ->from('2025-03-15')
                ->addPeriod('09:00', '10:00') // Morning
                ->addPeriod('10:30', '11:30') // Late morning
                ->save();

            // Look for 30-minute slot - should find first available slot (earliest)
            $nextSlot = $user->getNextBookableSlot('2025-03-15', 30);
            expect($nextSlot['start_time'])->toBe('08:00'); // First available slot
            expect($nextSlot['end_time'])->toBe('08:30');

            // Verify that the gap slot (10:00-10:30) is also available in the slots list
            $allSlots = $user->getBookableSlots('2025-03-15', 30);
            $gapSlot = collect($allSlots)->firstWhere('start_time', '10:00');
            expect($gapSlot)->not->toBeNull();
            expect($gapSlot['is_available'])->toBeTrue();
            expect($gapSlot['end_time'])->toBe('10:30');

            // Look for 45-minute slot - gap is too small, should find at very beginning
            $nextSlot45 = $user->getNextBookableSlot('2025-03-15', 45);
            expect($nextSlot45['start_time'])->toBe('08:00'); // Should find at the very beginning before any blocks
            expect($nextSlot45['end_time'])->toBe('08:45');
        });

    });

    describe('Performance with complex schedules', function () {

        it('performs well with many recurring schedules', function () {
            $user = createUser();

            // Create availability schedule
            Zap::for($user)
                ->availability()
                ->from('2025-03-17')
                ->addPeriod('08:00', '20:00')
                ->weekly(['monday', 'wednesday', 'friday'])
                ->save();

            // Create 10 different recurring schedules
            for ($i = 0; $i < 10; $i++) {
                $startHour = 9 + $i;
                $endHour = $startHour + 1;

                Zap::for($user)
                    ->named("Schedule {$i}")
                    ->from('2025-03-15')
                    ->addPeriod(sprintf('%02d:00', $startHour), sprintf('%02d:00', $endHour))
                    ->weekly(['monday', 'wednesday', 'friday'])
                    ->save();
            }

            $startTime = microtime(true);

            $slots = $user->getBookableSlots('2025-03-17', 60); // Monday
            $nextSlot = $user->getNextBookableSlot('2025-03-17', 120);

            $executionTime = microtime(true) - $startTime;

            expect($executionTime)->toBeLessThan(0.2); // Should complete quickly
            expect($slots)->toBeArray();
            expect(count($slots))->toBeGreaterThan(0);
        });

    })->skip();

});
