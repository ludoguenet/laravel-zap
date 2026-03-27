<?php

use Carbon\Carbon;
use Zap\Data\DailyFrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\AnnuallyFrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\BiMonthlyFrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\MonthlyFrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\MonthlyOrdinalWeekdayFrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\QuarterlyFrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\SemiAnnuallyFrequencyConfig;
use Zap\Data\WeeklyFrequencyConfig\BiWeeklyFrequencyConfig;
use Zap\Data\WeeklyFrequencyConfig\WeeklyFrequencyConfig;
use Zap\Models\Schedule;

describe('FrequencyConfig getNextRecurrence', function () {

    beforeEach(function () {
        Carbon::setTestNow('2025-01-01 00:00:00');
    });

    afterEach(function () {
        Carbon::setTestNow();
    });

    it('advances daily by one day', function () {
        $config = DailyFrequencyConfig::fromArray([]);
        $next = $config->getNextRecurrence(Carbon::parse('2025-01-01'));

        expect($next->toDateString())->toBe('2025-01-02');
    });

    it('advances weekly across multiple allowed days', function () {
        $config = WeeklyFrequencyConfig::fromArray(['days' => ['monday', 'thursday']]);

        $fromWednesday = $config->getNextRecurrence(Carbon::parse('2025-01-01')); // Wednesday
        $fromThursday = $config->getNextRecurrence(Carbon::parse('2025-01-02')); // Thursday

        expect($fromWednesday->toDateString())->toBe('2025-01-02'); // Next day in same week
        expect($fromThursday->toDateString())->toBe('2025-01-06');  // Wraps to Monday of next week
    });

    it('advances bi-weekly respecting anchor week and allowed days', function () {
        $config = BiWeeklyFrequencyConfig::fromArray(['days' => ['monday', 'wednesday']]);
        $config->setStartFromStartDate(Carbon::parse('2025-01-06')); // anchor to week starting Jan 6 (Monday)

        $fromStartMonday = $config->getNextRecurrence(Carbon::parse('2025-01-06'));
        $fromStartWednesday = $config->getNextRecurrence(Carbon::parse('2025-01-08'));

        expect($fromStartMonday->toDateString())->toBe('2025-01-08');    // Next allowed day in the same anchor week
        expect($fromStartWednesday->toDateString())->toBe('2025-01-20'); // Next allowed day in the following eligible week
    });

    it('advances monthly across multiple days of month', function () {
        $config = MonthlyFrequencyConfig::fromArray(['days_of_month' => [5, 20]]);

        $fromFive = $config->getNextRecurrence(Carbon::parse('2025-01-05'));
        $fromTwenty = $config->getNextRecurrence(Carbon::parse('2025-01-20'));

        expect($fromFive->toDateString())->toBe('2025-01-20');
        expect($fromTwenty->toDateString())->toBe('2025-02-05');
    });

    it('advances bi-monthly while honoring multiple days of month', function () {
        $config = BiMonthlyFrequencyConfig::fromArray(['days_of_month' => [5, 20]]);
        $config->setStartFromStartDate(Carbon::parse('2025-01-05'));

        $fromFive = $config->getNextRecurrence(Carbon::parse('2025-01-05'));
        $fromTwenty = $config->getNextRecurrence(Carbon::parse('2025-01-20'));

        expect($fromFive->toDateString())->toBe('2025-01-20');   // Later in same start month
        expect($fromTwenty->toDateString())->toBe('2025-03-05'); // Two months ahead
    });

    it('advances quarterly using configured day of month', function () {
        $config = QuarterlyFrequencyConfig::fromArray(['days_of_month' => [15]]);
        $config->setStartFromStartDate(Carbon::parse('2025-02-15'));

        $fromStart = $config->getNextRecurrence(Carbon::parse('2025-02-15'));
        $fromEarlyMonth = $config->getNextRecurrence(Carbon::parse('2025-02-01'));

        expect($fromStart->toDateString())->toBe('2025-05-15');
        expect($fromEarlyMonth->toDateString())->toBe('2025-02-15');
    });

    it('advances semi-annually using configured day of month', function () {
        $config = SemiAnnuallyFrequencyConfig::fromArray(['days_of_month' => [10]]);
        $config->setStartFromStartDate(Carbon::parse('2025-01-10'));

        $fromStart = $config->getNextRecurrence(Carbon::parse('2025-01-10'));
        $fromEarlyMonth = $config->getNextRecurrence(Carbon::parse('2025-01-05'));

        expect($fromStart->toDateString())->toBe('2025-07-10');
        expect($fromEarlyMonth->toDateString())->toBe('2025-01-10');
    });

    it('advances annually using configured days of month', function () {
        $config = AnnuallyFrequencyConfig::fromArray(['days_of_month' => [1, 15]]);
        $config->setStartFromStartDate(Carbon::parse('2025-04-01'));

        $fromStart = $config->getNextRecurrence(Carbon::parse('2025-04-01'));
        $fromLaterInYear = $config->getNextRecurrence(Carbon::parse('2025-04-20'));

        expect($fromStart->toDateString())->toBe('2025-04-15');  // Next day in same start month
        expect($fromLaterInYear->toDateString())->toBe('2026-04-01'); // Rolls to next year
    });

    it('advances monthly ordinal weekday (1st Wednesday, 2nd Friday, last Monday)', function () {
        $firstWed = MonthlyOrdinalWeekdayFrequencyConfig::fromArray(['ordinal' => 1, 'day_of_week' => 3]);
        $secondFri = MonthlyOrdinalWeekdayFrequencyConfig::fromArray(['ordinal' => 2, 'day_of_week' => 5]);
        $lastMon = MonthlyOrdinalWeekdayFrequencyConfig::fromArray(['ordinal' => 5, 'day_of_week' => 1]);

        // Jan 2025: 1st Wednesday = Jan 1
        expect($firstWed->getNextRecurrence(Carbon::parse('2025-01-01'))->toDateString())->toBe('2025-02-05');
        // Jan 2025: 2nd Friday = Jan 10
        expect($secondFri->getNextRecurrence(Carbon::parse('2025-01-10'))->toDateString())->toBe('2025-02-14');
        // Jan 2025: last Monday = Jan 27
        expect($lastMon->getNextRecurrence(Carbon::parse('2025-01-27'))->toDateString())->toBe('2025-02-24');
    });
});

describe('MonthlyOrdinalWeekdayFrequencyConfig', function () {

    it('shouldCreateInstance returns true only on the ordinal weekday', function () {
        $config = MonthlyOrdinalWeekdayFrequencyConfig::fromArray(['ordinal' => 1, 'day_of_week' => 3]); // 1st Wednesday

        expect($config->shouldCreateInstance(Carbon::parse('2025-01-01')))->toBeTrue();   // Jan 1 2025 = Wednesday
        expect($config->shouldCreateInstance(Carbon::parse('2025-01-08')))->toBeFalse();  // 2nd Wednesday
        expect($config->shouldCreateInstance(Carbon::parse('2025-02-05')))->toBeTrue();  // 1st Wed Feb
        expect($config->shouldCreateInstance(Carbon::parse('2025-02-04')))->toBeFalse(); // Tuesday
    });

    it('shouldCreateInstance for second Friday', function () {
        $config = MonthlyOrdinalWeekdayFrequencyConfig::fromArray(['ordinal' => 2, 'day_of_week' => 5]);

        expect($config->shouldCreateInstance(Carbon::parse('2025-01-10')))->toBeTrue();  // 2nd Fri Jan 2025
        expect($config->shouldCreateInstance(Carbon::parse('2025-01-03')))->toBeFalse(); // 1st Fri
        expect($config->shouldCreateInstance(Carbon::parse('2025-02-14')))->toBeTrue();  // 2nd Fri Feb
    });

    it('shouldCreateInstance for last Monday', function () {
        $config = MonthlyOrdinalWeekdayFrequencyConfig::fromArray(['ordinal' => 5, 'day_of_week' => 1]);

        expect($config->shouldCreateInstance(Carbon::parse('2025-01-27')))->toBeTrue();  // last Mon Jan 2025
        expect($config->shouldCreateInstance(Carbon::parse('2025-02-24')))->toBeTrue();  // last Mon Feb 2025
        expect($config->shouldCreateInstance(Carbon::parse('2025-01-20')))->toBeFalse(); // 3rd Mon
    });

    it('fromArray accepts ordinal string "last"', function () {
        $config = MonthlyOrdinalWeekdayFrequencyConfig::fromArray(['ordinal' => 'last', 'day_of_week' => 1]);

        expect($config->getOrdinal())->toBe(5);
    });

    it('fromArray accepts day name for day_of_week', function () {
        $config = MonthlyOrdinalWeekdayFrequencyConfig::fromArray(['ordinal' => 1, 'day' => 'wednesday']);

        expect($config->getDayOfWeek())->toBe(3);
    });

    it('throws when ordinal is missing', function () {
        expect(fn () => MonthlyOrdinalWeekdayFrequencyConfig::fromArray(['day_of_week' => 1]))
            ->toThrow(InvalidArgumentException::class, 'ordinal');
    });

    it('throws when day_of_week and day are missing', function () {
        expect(fn () => MonthlyOrdinalWeekdayFrequencyConfig::fromArray(['ordinal' => 1]))
            ->toThrow(InvalidArgumentException::class);
    });

    it('shouldCreateRecurringInstance returns false when date is before schedule start', function () {
        $config = MonthlyOrdinalWeekdayFrequencyConfig::fromArray(['ordinal' => 1, 'day_of_week' => 3]);
        $schedule = new Schedule;
        $schedule->start_date = Carbon::parse('2025-02-01');

        expect($config->shouldCreateRecurringInstance($schedule, Carbon::parse('2025-01-01')))->toBeFalse();
        expect($config->shouldCreateRecurringInstance($schedule, Carbon::parse('2025-02-05')))->toBeTrue();
    });

    it('fourth and last differ when month has five occurrences', function () {
        $fourthFri = MonthlyOrdinalWeekdayFrequencyConfig::fromArray(['ordinal' => 4, 'day_of_week' => 5]);
        $lastFri = MonthlyOrdinalWeekdayFrequencyConfig::fromArray(['ordinal' => 5, 'day_of_week' => 5]);

        // March 2025: 4th Friday = Mar 28, last Friday = Mar 28 (only 4 Fridays in March 2025)
        expect($fourthFri->shouldCreateInstance(Carbon::parse('2025-03-28')))->toBeTrue();
        expect($lastFri->shouldCreateInstance(Carbon::parse('2025-03-28')))->toBeTrue();
        // January 2025 has 5 Fridays: 3, 10, 17, 24, 31. 4th = 24th, last = 31st
        expect($fourthFri->shouldCreateInstance(Carbon::parse('2025-01-24')))->toBeTrue();
        expect($fourthFri->shouldCreateInstance(Carbon::parse('2025-01-31')))->toBeFalse();
        expect($lastFri->shouldCreateInstance(Carbon::parse('2025-01-31')))->toBeTrue();
    });
});
