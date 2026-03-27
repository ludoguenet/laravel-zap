<?php

use Carbon\Carbon;
use Zap\Data\WeeklyFrequencyConfig\BiWeeklyFrequencyConfig;
use Zap\Data\WeeklyFrequencyConfig\EveryXWeeksFrequencyConfig;
use Zap\Data\WeeklyFrequencyConfig\WeeklyFrequencyConfig;

describe('Timezone handling in weekly frequency configs', function () {

    beforeEach(function () {
        config(['app.timezone' => 'UTC']);
    });

    afterEach(function () {
        Carbon::setTestNow();
    });

    it('handles Tokyo timezone correctly in BiWeeklyFrequencyConfig constructor', function () {
        $tokyoDate = Carbon::parse('2026-03-23 02:00:00', 'Asia/Tokyo');

        $config = new BiWeeklyFrequencyConfig(
            days: ['monday'],
            startsOn: $tokyoDate
        );

        expect($config->startsOn)->not->toBeNull();
        expect($config->startsOn->dayOfWeek)->toBe(Carbon::MONDAY);
        expect($config->startsOn->format('Y-m-d'))->toBe('2026-03-23');
    });

    it('handles Tokyo timezone correctly in WeeklyFrequencyConfig constructor', function () {
        $tokyoDate = Carbon::parse('2026-03-23 02:00:00', 'Asia/Tokyo');

        $config = new WeeklyFrequencyConfig(
            days: ['monday'],
            startsOn: $tokyoDate
        );

        expect($config->startsOn)->not->toBeNull();
        expect($config->startsOn->dayOfWeek)->toBe(Carbon::MONDAY);
        expect($config->startsOn->format('Y-m-d'))->toBe('2026-03-23');
    });

    it('handles Tokyo timezone correctly in EveryXWeeksFrequencyConfig constructor', function () {
        $tokyoDate = Carbon::parse('2026-03-23 02:00:00', 'Asia/Tokyo');

        $config = new EveryXWeeksFrequencyConfig(
            frequencyWeeks: 3,
            days: ['monday'],
            startsOn: $tokyoDate
        );

        expect($config->startsOn)->not->toBeNull();
        expect($config->startsOn->dayOfWeek)->toBe(Carbon::MONDAY);
        expect($config->startsOn->format('Y-m-d'))->toBe('2026-03-23');
    });

    it('produces consistent week calculations for same logical date in different timezones', function () {
        $utcDate = Carbon::parse('2026-03-23 12:00:00', 'UTC');
        $tokyoDate = Carbon::parse('2026-03-23 21:00:00', 'Asia/Tokyo');
        $nyDate = Carbon::parse('2026-03-23 08:00:00', 'America/New_York');

        $configUtc = new BiWeeklyFrequencyConfig(days: ['monday'], startsOn: $utcDate);
        $configTokyo = new BiWeeklyFrequencyConfig(days: ['monday'], startsOn: $tokyoDate);
        $configNy = new BiWeeklyFrequencyConfig(days: ['monday'], startsOn: $nyDate);

        expect($configUtc->startsOn->format('Y-m-d'))->toBe('2026-03-23');
        expect($configTokyo->startsOn->format('Y-m-d'))->toBe('2026-03-23');
        expect($configNy->startsOn->format('Y-m-d'))->toBe('2026-03-23');
    });

    it('setStartFromStartDate normalizes timezone before calculating start of week', function () {
        $tokyoDate = Carbon::parse('2026-03-23 02:00:00', 'Asia/Tokyo');

        $config = new BiWeeklyFrequencyConfig(days: ['monday']);
        $config->setStartFromStartDate($tokyoDate);

        expect($config->startsOn)->not->toBeNull();
        expect($config->startsOn->dayOfWeek)->toBe(Carbon::MONDAY);
        expect($config->startsOn->format('Y-m-d'))->toBe('2026-03-23');
    });

    it('getNextRecurrence handles timezone correctly in EveryXWeeksFrequencyConfig', function () {
        $tokyoDate = Carbon::parse('2026-03-23 02:00:00', 'Asia/Tokyo');

        $config = new EveryXWeeksFrequencyConfig(
            frequencyWeeks: 2,
            days: ['monday'],
            startsOn: $tokyoDate
        );

        $nextRecurrence = $config->getNextRecurrence($tokyoDate);

        expect($nextRecurrence->dayOfWeek)->toBe(Carbon::MONDAY);
    });

    it('shouldCreateInstance works correctly with different timezones', function () {
        $tokyoMonday = Carbon::parse('2026-03-23 02:00:00', 'Asia/Tokyo');

        $config = new BiWeeklyFrequencyConfig(
            days: ['monday'],
            startsOn: $tokyoMonday
        );

        $checkDate = Carbon::parse('2026-04-06', 'UTC');

        expect($config->shouldCreateInstance($checkDate))->toBeTrue();
    });

    it('handles edge case where early morning Tokyo time is previous day in UTC', function () {
        $tokyoEarlyMorning = Carbon::parse('2026-03-23 01:00:00', 'Asia/Tokyo');

        $config = new BiWeeklyFrequencyConfig(
            days: ['monday'],
            startsOn: $tokyoEarlyMorning
        );

        expect($config->startsOn->dayOfWeek)->toBe(Carbon::MONDAY);
        expect($config->startsOn->format('Y-m-d'))->toBe('2026-03-23');
    });

    it('respects app timezone configuration', function () {
        config(['app.timezone' => 'Europe/Paris']);

        $tokyoDate = Carbon::parse('2026-03-23 02:00:00', 'Asia/Tokyo');

        $config = new BiWeeklyFrequencyConfig(
            days: ['monday'],
            startsOn: $tokyoDate
        );

        expect($config->startsOn->timezone->getName())->toBe('Europe/Paris');
    });

    it('handles string date input with timezone correctly', function () {
        $config = new BiWeeklyFrequencyConfig(
            days: ['monday'],
            startsOn: '2026-03-23 02:00:00'
        );

        expect($config->startsOn)->not->toBeNull();
        expect($config->startsOn->dayOfWeek)->toBe(Carbon::MONDAY);
    });

});
