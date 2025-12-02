<?php

use Carbon\Carbon;
use Zap\Data\AnnuallyFrequencyConfig;
use Zap\Data\BiMonthlyFrequencyConfig;
use Zap\Data\BiWeeklyFrequencyConfig;
use Zap\Data\DailyFrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig;
use Zap\Data\QuarterlyFrequencyConfig;
use Zap\Data\SemiAnnuallyFrequencyConfig;
use Zap\Data\WeeklyFrequencyConfig;

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
});
