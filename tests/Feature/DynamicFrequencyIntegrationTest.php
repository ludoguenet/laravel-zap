<?php

use Carbon\Carbon;
use Zap\Data\MonthlyFrequencyConfig\EveryXMonthsFrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\MonthlyOrdinalWeekdayFrequencyConfig;
use Zap\Data\WeeklyFrequencyConfig\EveryXWeeksFrequencyConfig;
use Zap\Facades\Zap;
use Zap\Models\Schedule;

describe('Dynamic Frequency Integration', function () {

    describe('Persistence', function () {

        it('can save and retrieve everyThreeWeeks schedule', function () {
            $user = createUser();

            $schedule = Zap::for($user)
                ->named('Every 3 Weeks Meeting')
                ->from('2025-01-06')
                ->to('2025-12-31')
                ->addPeriod('09:00', '10:00')
                ->everyThreeWeeks(['monday'])
                ->save();

            expect($schedule)->toBeInstanceOf(Schedule::class);
            expect($schedule->name)->toBe('Every 3 Weeks Meeting');
            expect($schedule->is_recurring)->toBeTrue();
            expect($schedule->frequency)->toBe('every_3_weeks');
            expect($schedule->frequency_config)->toBeInstanceOf(EveryXWeeksFrequencyConfig::class);
            expect($schedule->frequency_config->getFrequencyWeeks())->toBe(3);
            expect($schedule->frequency_config->days)->toBe(['monday']);

            // Retrieve from database
            $retrieved = Schedule::find($schedule->id);
            expect($retrieved->frequency)->toBe('every_3_weeks');
            expect($retrieved->frequency_config)->toBeInstanceOf(EveryXWeeksFrequencyConfig::class);
            expect($retrieved->frequency_config->getFrequencyWeeks())->toBe(3);
        });

        it('can save and retrieve everyFourMonths schedule', function () {
            $user = createUser();

            $schedule = Zap::for($user)
                ->named('Quarterly Review')
                ->from('2025-01-15')
                ->to('2025-12-31')
                ->addPeriod('14:00', '15:00')
                ->everyFourMonths(['day_of_month' => 15])
                ->save();

            expect($schedule)->toBeInstanceOf(Schedule::class);
            expect($schedule->frequency)->toBe('every_4_months');
            expect($schedule->frequency_config)->toBeInstanceOf(EveryXMonthsFrequencyConfig::class);
            expect($schedule->frequency_config->getFrequencyMonths())->toBe(4);
            expect($schedule->frequency_config->days_of_month)->toBe([15]);

            // Retrieve from database
            $retrieved = Schedule::find($schedule->id);
            expect($retrieved->frequency)->toBe('every_4_months');
            expect($retrieved->frequency_config)->toBeInstanceOf(EveryXMonthsFrequencyConfig::class);
            expect($retrieved->frequency_config->getFrequencyMonths())->toBe(4);
        });

        it('can save and retrieve everyFiveWeeks with startsOn', function () {
            $user = createUser();

            $schedule = Zap::for($user)
                ->named('Every 5 Weeks Sync')
                ->from('2025-01-13')
                ->addPeriod('10:00', '11:00')
                ->everyFiveWeeks(['wednesday', 'friday'], '2025-01-06')
                ->save();

            expect($schedule->frequency)->toBe('every_5_weeks');
            expect($schedule->frequency_config)->toBeInstanceOf(EveryXWeeksFrequencyConfig::class);
            expect($schedule->frequency_config->getFrequencyWeeks())->toBe(5);
            expect($schedule->frequency_config->days)->toBe(['wednesday', 'friday']);
            expect($schedule->frequency_config->startsOn->toDateString())->toBe('2025-01-06');

            // Retrieve from database
            $retrieved = Schedule::find($schedule->id);
            expect($retrieved->frequency_config)->toBeInstanceOf(EveryXWeeksFrequencyConfig::class);
            expect($retrieved->frequency_config->startsOn->toDateString())->toBe('2025-01-06');
        });

        it('can save and retrieve everySixWeeks schedule', function () {
            $user = createUser();

            $schedule = Zap::for($user)
                ->named('Every 6 Weeks Check-in')
                ->from('2025-01-01')
                ->addPeriod('09:00', '10:00')
                ->everySixWeeks(['tuesday'])
                ->save();

            expect($schedule->frequency)->toBe('every_6_weeks');

            $retrieved = Schedule::find($schedule->id);
            expect($retrieved->frequency_config)->toBeInstanceOf(EveryXWeeksFrequencyConfig::class);
            expect($retrieved->frequency_config->getFrequencyWeeks())->toBe(6);
        });

        it('can save and retrieve everySevenMonths schedule', function () {
            $user = createUser();

            $schedule = Zap::for($user)
                ->named('Semi-annual Plus')
                ->from('2025-02-01')
                ->addPeriod('09:00', '10:00')
                ->everySevenMonths(['days_of_month' => [1, 15], 'start_month' => 2])
                ->save();

            expect($schedule->frequency)->toBe('every_7_months');

            $retrieved = Schedule::find($schedule->id);
            expect($retrieved->frequency_config)->toBeInstanceOf(EveryXMonthsFrequencyConfig::class);
            expect($retrieved->frequency_config->getFrequencyMonths())->toBe(7);
            expect($retrieved->frequency_config->days_of_month)->toBe([1, 15]);
            expect($retrieved->frequency_config->start_month)->toBe(2);
        });

    });

    describe('Cast Reconstruction', function () {

        it('correctly reconstructs EveryXWeeksFrequencyConfig from database', function () {
            $user = createUser();

            $schedule = Zap::for($user)
                ->named('Test')
                ->from('2025-01-01')
                ->addPeriod('09:00', '10:00')
                ->everyEightWeeks(['monday'])
                ->save();

            // Clear any cached models
            Schedule::query()->getConnection()->commit();

            // Fresh query to trigger cast reconstruction
            $fresh = Schedule::query()->find($schedule->id);

            expect($fresh->frequency_config)->toBeInstanceOf(EveryXWeeksFrequencyConfig::class);
            expect($fresh->frequency_config->getFrequencyWeeks())->toBe(8);
            expect($fresh->frequency_config->days)->toBe(['monday']);
        });

        it('correctly reconstructs EveryXMonthsFrequencyConfig from database', function () {
            $user = createUser();

            $schedule = Zap::for($user)
                ->named('Test')
                ->from('2025-01-10')
                ->addPeriod('09:00', '10:00')
                ->everyTenMonths(['day_of_month' => 10])
                ->save();

            // Fresh query to trigger cast reconstruction
            $fresh = Schedule::query()->find($schedule->id);

            expect($fresh->frequency_config)->toBeInstanceOf(EveryXMonthsFrequencyConfig::class);
            expect($fresh->frequency_config->getFrequencyMonths())->toBe(10);
            expect($fresh->frequency_config->days_of_month)->toBe([10]);
        });

    });

    describe('Frequency Config Methods', function () {

        it('EveryXWeeksFrequencyConfig getNextRecurrence works correctly', function () {
            $user = createUser();

            $schedule = Zap::for($user)
                ->named('Test')
                ->from('2025-01-06')
                ->addPeriod('09:00', '10:00')
                ->everyThreeWeeks(['monday'])
                ->save();

            $config = $schedule->frequency_config;
            $current = Carbon::parse('2025-01-06');
            $next = $config->getNextRecurrence($current);

            // Should be 3 weeks later (next Monday that's 3 weeks from start)
            expect($next->isMonday())->toBeTrue();
            expect($next->diffInWeeks($current) % 3)->toBe(0);
        });

        it('EveryXMonthsFrequencyConfig getNextRecurrence works correctly', function () {
            $user = createUser();

            $schedule = Zap::for($user)
                ->named('Test')
                ->from('2025-01-15')
                ->addPeriod('09:00', '10:00')
                ->everyFourMonths(['day_of_month' => 15])
                ->save();

            $config = $schedule->frequency_config;
            $current = Carbon::parse('2025-01-15');
            $next = $config->getNextRecurrence($current);

            // Should be 4 months later on the 15th
            expect($next->day)->toBe(15);
            expect($next->month)->toBe(5); // January + 4 = May
        });

        it('EveryXWeeksFrequencyConfig shouldCreateRecurringInstance works correctly', function () {
            $user = createUser();

            $schedule = Zap::for($user)
                ->named('Test')
                ->from('2025-01-06')
                ->addPeriod('09:00', '10:00')
                ->everyThreeWeeks(['monday'])
                ->save();

            $config = $schedule->frequency_config;

            // Week 0 (start) - should be true
            expect($config->shouldCreateRecurringInstance($schedule, Carbon::parse('2025-01-06')))->toBeTrue();

            // Week 1 - should be false
            expect($config->shouldCreateRecurringInstance($schedule, Carbon::parse('2025-01-13')))->toBeFalse();

            // Week 2 - should be false
            expect($config->shouldCreateRecurringInstance($schedule, Carbon::parse('2025-01-20')))->toBeFalse();

            // Week 3 - should be true
            expect($config->shouldCreateRecurringInstance($schedule, Carbon::parse('2025-01-27')))->toBeTrue();
        });

        it('EveryXMonthsFrequencyConfig shouldCreateRecurringInstance works correctly', function () {
            $user = createUser();

            $schedule = Zap::for($user)
                ->named('Test')
                ->from('2025-01-15')
                ->addPeriod('09:00', '10:00')
                ->everyFourMonths(['day_of_month' => 15])
                ->save();

            $config = $schedule->frequency_config;

            // Month 0 (January) - should be true
            expect($config->shouldCreateRecurringInstance($schedule, Carbon::parse('2025-01-15')))->toBeTrue();

            // Month 2 (March) - should be false
            expect($config->shouldCreateRecurringInstance($schedule, Carbon::parse('2025-03-15')))->toBeFalse();

            // Month 4 (May) - should be true
            expect($config->shouldCreateRecurringInstance($schedule, Carbon::parse('2025-05-15')))->toBeTrue();
        });

    });

    describe('Monthly ordinal weekday (first/second/last X of month)', function () {

        it('can save and retrieve firstWednesdayOfMonth schedule', function () {
            $user = createUser();

            $schedule = Zap::for($user)
                ->named('1st Wednesday Meeting')
                ->from('2025-01-01')
                ->to('2025-12-31')
                ->addPeriod('09:00', '10:00')
                ->firstWednesdayOfMonth()
                ->save();

            expect($schedule)->toBeInstanceOf(Schedule::class);
            expect($schedule->frequency)->toBe('monthly_ordinal_weekday');
            expect($schedule->frequency_config)->toBeInstanceOf(MonthlyOrdinalWeekdayFrequencyConfig::class);
            expect($schedule->frequency_config->getOrdinal())->toBe(1);
            expect($schedule->frequency_config->getDayOfWeek())->toBe(3); // Wednesday

            $retrieved = Schedule::find($schedule->id);
            expect($retrieved->frequency_config)->toBeInstanceOf(MonthlyOrdinalWeekdayFrequencyConfig::class);
        });

        it('can save and retrieve secondFridayOfMonth and lastMondayOfMonth', function () {
            $user = createUser();

            $secondFri = Zap::for($user)
                ->named('2nd Friday')
                ->from('2025-01-01')
                ->addPeriod('14:00', '15:00')
                ->secondFridayOfMonth()
                ->save();

            expect($secondFri->frequency)->toBe('monthly_ordinal_weekday');
            expect($secondFri->frequency_config->getOrdinal())->toBe(2);
            expect($secondFri->frequency_config->getDayOfWeek())->toBe(5);

            $lastMon = Zap::for($user)
                ->named('Last Monday')
                ->from('2025-01-01')
                ->addPeriod('10:00', '11:00')
                ->lastMondayOfMonth()
                ->save();

            expect($lastMon->frequency)->toBe('monthly_ordinal_weekday');
            expect($lastMon->frequency_config->getOrdinal())->toBe(5);
            expect($lastMon->frequency_config->getDayOfWeek())->toBe(1);
        });

        it('correctly reconstructs MonthlyOrdinalWeekdayFrequencyConfig from database', function () {
            $user = createUser();

            $schedule = Zap::for($user)
                ->named('Test')
                ->from('2025-01-01')
                ->addPeriod('09:00', '10:00')
                ->firstWednesdayOfMonth()
                ->save();

            $fresh = Schedule::query()->find($schedule->id);

            expect($fresh->frequency_config)->toBeInstanceOf(MonthlyOrdinalWeekdayFrequencyConfig::class);
            expect($fresh->frequency_config->getOrdinal())->toBe(1);
            expect($fresh->frequency_config->getDayOfWeek())->toBe(3);
        });

        it('blocks time only on 1st Wednesday of each month', function () {
            $user = createUser();

            Zap::for($user)
                ->named('1st Wednesday Standup')
                ->from('2025-01-01')
                ->to('2025-12-31')
                ->addPeriod('09:00', '10:00')
                ->firstWednesdayOfMonth()
                ->save();

            // Jan 1 2025 = Wednesday = 1st Wednesday
            expect($user->isAvailableAt('2025-01-01', '09:00', '10:00'))->toBeFalse();
            // Jan 8 = 2nd Wednesday - should not be blocked
            expect($user->isAvailableAt('2025-01-08', '09:00', '10:00'))->toBeTrue();
            // Feb 5 2025 = 1st Wednesday
            expect($user->isAvailableAt('2025-02-05', '09:00', '10:00'))->toBeFalse();
            // Feb 4 = Tuesday - available
            expect($user->isAvailableAt('2025-02-04', '09:00', '10:00'))->toBeTrue();
        });

        it('blocks time only on 2nd Friday of each month', function () {
            $user = createUser();

            Zap::for($user)
                ->named('2nd Friday Review')
                ->from('2025-01-01')
                ->to('2025-12-31')
                ->addPeriod('14:00', '15:00')
                ->secondFridayOfMonth()
                ->save();

            // Jan 10 2025 = 2nd Friday
            expect($user->isAvailableAt('2025-01-10', '14:00', '15:00'))->toBeFalse();
            // Jan 3 = 1st Friday - available
            expect($user->isAvailableAt('2025-01-03', '14:00', '15:00'))->toBeTrue();
            // Feb 14 2025 = 2nd Friday
            expect($user->isAvailableAt('2025-02-14', '14:00', '15:00'))->toBeFalse();
        });

        it('blocks time only on last Monday of each month', function () {
            $user = createUser();

            Zap::for($user)
                ->named('Last Monday Retro')
                ->from('2025-01-01')
                ->to('2025-12-31')
                ->addPeriod('16:00', '17:00')
                ->lastMondayOfMonth()
                ->save();

            // Jan 27 2025 = last Monday of January
            expect($user->isAvailableAt('2025-01-27', '16:00', '17:00'))->toBeFalse();
            // Jan 20 = 3rd Monday - available
            expect($user->isAvailableAt('2025-01-20', '16:00', '17:00'))->toBeTrue();
            // Feb 24 2025 = last Monday of February
            expect($user->isAvailableAt('2025-02-24', '16:00', '17:00'))->toBeFalse();
        });

    });

});
