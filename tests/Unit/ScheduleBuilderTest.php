<?php

use Zap\Builders\ScheduleBuilder;
use Zap\Data\AnnuallyFrequencyConfig;
use Zap\Data\BiMonthlyFrequencyConfig;
use Zap\Data\BiWeeklyFrequencyConfig;
use Zap\Data\QuarterlyFrequencyConfig;
use Zap\Data\SemiAnnuallyFrequencyConfig;
use Zap\Enums\Frequency;
use Zap\Models\Schedule;

describe('ScheduleBuilder', function () {

    it('can build schedule attributes correctly', function () {
        $user = createUser();

        $builder = new ScheduleBuilder;
        $built = $builder
            ->for($user)
            ->named('Test Meeting')
            ->description('A test meeting')
            ->from('2025-01-01')
            ->to('2025-12-31')
            ->addPeriod('09:00', '10:00')
            ->weekly(['monday'])
            ->withMetadata(['room' => 'A'])
            ->build();

        expect($built['attributes'])->toHaveKey('name', 'Test Meeting');
        expect($built['attributes'])->toHaveKey('description', 'A test meeting');
        expect($built['attributes'])->toHaveKey('start_date', '2025-01-01');
        expect($built['attributes'])->toHaveKey('end_date', '2025-12-31');
        expect($built['attributes'])->toHaveKey('is_recurring', true);
        expect($built['attributes'])->toHaveKey('frequency', Frequency::WEEKLY);
        expect($built['periods'])->toHaveCount(1);
        expect($built['periods'][0])->toMatchArray([
            'start_time' => '09:00',
            'end_time' => '10:00',
            'date' => '2025-01-01',
        ]);
    });

    it('can add multiple periods', function () {
        $user = createUser();

        $builder = new ScheduleBuilder;
        $built = $builder
            ->for($user)
            ->from('2025-01-01')
            ->addPeriod('09:00', '10:00')
            ->addPeriod('14:00', '15:00')
            ->addPeriods([
                ['start_time' => '16:00', 'end_time' => '17:00'],
            ])
            ->build();

        expect($built['periods'])->toHaveCount(3);
        expect($built['periods'][0]['start_time'])->toBe('09:00');
        expect($built['periods'][1]['start_time'])->toBe('14:00');
        expect($built['periods'][2]['start_time'])->toBe('16:00');
    });

    it('can set different recurring frequencies', function () {
        $user = createUser();

        $builder = new ScheduleBuilder;

        // Test daily
        $daily = $builder->for($user)->from('2025-01-01')->daily()->build();
        expect($daily['attributes']['frequency'])->toBe(Frequency::DAILY);

        // Test weekly
        $builder->reset();
        $weekly = $builder->for($user)->from('2025-01-01')->weekly(['monday', 'friday'])->build();
        expect($weekly['attributes']['frequency'])->toBe(Frequency::WEEKLY);
        expect($weekly['attributes']['frequency_config']->toArray())->toBe(['days' => ['monday', 'friday']]);

        // Test weekDays (convenience method that combines weekly and addPeriod)
        $builder->reset();
        $weekDays = $builder->for($user)->from('2025-01-01')->weekDays(['monday', 'wednesday', 'friday'], '09:00', '17:00')->build();
        expect($weekDays['attributes']['frequency'])->toBe(Frequency::WEEKLY);
        expect($weekDays['attributes']['frequency_config']->toArray())->toBe(['days' => ['monday', 'wednesday', 'friday']]);
        expect($weekDays['periods'])->toHaveCount(1);
        expect($weekDays['periods'][0])->toMatchArray([
            'start_time' => '09:00',
            'end_time' => '17:00',
            'date' => '2025-01-01',
        ]);

        // Test monthly
        $builder->reset();
        $monthly = $builder->for($user)->from('2025-01-01')->monthly(['day_of_month' => 15])->build();
        expect($monthly['attributes']['frequency'])->toBe(Frequency::MONTHLY);
        expect($monthly['attributes']['frequency_config']->toArray())->toBe(['days_of_month' => [15]]);

        // Test bi-weekly
        $builder->reset();
        $biweekly = $builder->for($user)->from('2025-01-06')->biweekly(['monday'])->build();
        expect($biweekly['attributes']['frequency'])->toBe(Frequency::BIWEEKLY);
        expect($biweekly['attributes']['frequency_config'])->toBeInstanceOf(BiWeeklyFrequencyConfig::class);
        expect($biweekly['attributes']['frequency_config']->days)->toBe(['monday']);
        expect($biweekly['attributes']['frequency_config']->startsOn->toDateString())->toBe('2025-01-06');

        // Test bi-monthly
        $builder->reset();
        $bimonthly = $builder->for($user)->from('2025-01-05')->bimonthly(['day_of_month' => 5])->build();
        expect($bimonthly['attributes']['frequency'])->toBe(Frequency::BIMONTHLY);
        expect($bimonthly['attributes']['frequency_config'])->toBeInstanceOf(BiMonthlyFrequencyConfig::class);
        expect($bimonthly['attributes']['frequency_config']->days_of_month)->toBe([5]);
        expect($bimonthly['attributes']['frequency_config']->start_month)->toBe(1);

        // Test quarterly
        $builder->reset();
        $quarterly = $builder->for($user)->from('2025-02-15')->quarterly(['days_of_month' => [15]])->build();
        expect($quarterly['attributes']['frequency'])->toBe(Frequency::QUARTERLY);
        expect($quarterly['attributes']['frequency_config'])->toBeInstanceOf(QuarterlyFrequencyConfig::class);
        expect($quarterly['attributes']['frequency_config']->days_of_month)->toBe([15]);
        expect($quarterly['attributes']['frequency_config']->start_month)->toBe(2);

        // Test semi-annually
        $builder->reset();
        $semiannual = $builder->for($user)->from('2025-03-10')->semiannually(['day_of_month' => 10])->build();
        expect($semiannual['attributes']['frequency'])->toBe(Frequency::SEMIANNUALLY);
        expect($semiannual['attributes']['frequency_config'])->toBeInstanceOf(SemiAnnuallyFrequencyConfig::class);
        expect($semiannual['attributes']['frequency_config']->days_of_month)->toBe([10]);
        expect($semiannual['attributes']['frequency_config']->start_month)->toBe(3);

        // Test annually
        $builder->reset();
        $annually = $builder->for($user)->from('2025-04-01')->annually(['day_of_month' => 1])->build();
        expect($annually['attributes']['frequency'])->toBe(Frequency::ANNUALLY);
        expect($annually['attributes']['frequency_config'])->toBeInstanceOf(AnnuallyFrequencyConfig::class);
        expect($annually['attributes']['frequency_config']->days_of_month)->toBe([1]);
        expect($annually['attributes']['frequency_config']->start_month)->toBe(4);
    });

    it('respects custom anchors for recurring frequencies', function () {
        $user = createUser();
        $builder = new ScheduleBuilder;

        // Bi-weekly with explicit startsOn (should not be overridden by from())
        $biweekly = $builder
            ->for($user)
            ->from('2025-02-03')
            ->biweekly(['wednesday'], '2025-01-27') // Start anchor on previous Monday
            ->build();

        expect($biweekly['attributes']['frequency_config'])->toBeInstanceOf(BiWeeklyFrequencyConfig::class);
        expect($biweekly['attributes']['frequency_config']->startsOn->toDateString())->toBe('2025-01-27');
        expect($biweekly['attributes']['frequency_config']->days)->toBe(['wednesday']);

        // Bi-monthly with custom start_month and multiple days
        $builder->reset();
        $bimonthly = $builder
            ->for($user)
            ->from('2025-01-05')
            ->bimonthly(['days_of_month' => [3, 18], 'start_month' => 2])
            ->build();
        expect($bimonthly['attributes']['frequency_config'])->toBeInstanceOf(BiMonthlyFrequencyConfig::class);
        expect($bimonthly['attributes']['frequency_config']->days_of_month)->toBe([3, 18]);
        expect($bimonthly['attributes']['frequency_config']->start_month)->toBe(2);

        // Quarterly with custom start_month
        $builder->reset();
        $quarterly = $builder
            ->for($user)
            ->from('2025-01-10')
            ->quarterly(['day_of_month' => 7, 'start_month' => 4])
            ->build();
        expect($quarterly['attributes']['frequency_config'])->toBeInstanceOf(QuarterlyFrequencyConfig::class);
        expect($quarterly['attributes']['frequency_config']->days_of_month)->toBe([7]);
        expect($quarterly['attributes']['frequency_config']->start_month)->toBe(4);

        // Semi-annually with custom start_month
        $builder->reset();
        $semiannual = $builder
            ->for($user)
            ->from('2025-01-10')
            ->semiannually(['day_of_month' => 12, 'start_month' => 6])
            ->build();
        expect($semiannual['attributes']['frequency_config'])->toBeInstanceOf(SemiAnnuallyFrequencyConfig::class);
        expect($semiannual['attributes']['frequency_config']->days_of_month)->toBe([12]);
        expect($semiannual['attributes']['frequency_config']->start_month)->toBe(6);

        // Annually with custom start_month
        $builder->reset();
        $annually = $builder
            ->for($user)
            ->from('2025-01-10')
            ->annually(['day_of_month' => 25, 'start_month' => 9])
            ->build();
        expect($annually['attributes']['frequency_config'])->toBeInstanceOf(AnnuallyFrequencyConfig::class);
        expect($annually['attributes']['frequency_config']->days_of_month)->toBe([25]);
        expect($annually['attributes']['frequency_config']->start_month)->toBe(9);
    });

    it('can add validation rules', function () {
        $user = createUser();

        $builder = new ScheduleBuilder;
        $built = $builder
            ->for($user)
            ->from('2025-01-01')
            ->noOverlap()
            ->workingHoursOnly('09:00', '17:00')
            ->maxDuration(480)
            ->noWeekends()
            ->withRule('custom_rule', ['param' => 'value'])
            ->build();

        expect($built['rules'])->toHaveKey('no_overlap');
        expect($built['rules'])->toHaveKey('working_hours');
        expect($built['rules']['working_hours'])->toBe(['start' => '09:00', 'end' => '17:00']);
        expect($built['rules'])->toHaveKey('max_duration');
        expect($built['rules']['max_duration'])->toBe(['minutes' => 480]);
        expect($built['rules'])->toHaveKey('no_weekends');
        expect($built['rules'])->toHaveKey('custom_rule');
        expect($built['rules']['custom_rule'])->toBe(['param' => 'value']);
    });

    it('can handle metadata', function () {
        $user = createUser();

        $builder = new ScheduleBuilder;
        $built = $builder
            ->for($user)
            ->from('2025-01-01')
            ->withMetadata(['location' => 'Room A'])
            ->withMetadata(['priority' => 'high']) // Should merge
            ->build();

        expect($built['attributes']['metadata'])->toBe([
            'location' => 'Room A',
            'priority' => 'high',
        ]);
    });

    it('can set active/inactive status', function () {
        $user = createUser();

        $builder = new ScheduleBuilder;

        // Test active (default)
        $active = $builder->for($user)->from('2025-01-01')->active()->build();
        expect($active['attributes']['is_active'])->toBe(true);

        // Test inactive
        $builder->reset();
        $inactive = $builder->for($user)->from('2025-01-01')->inactive()->build();
        expect($inactive['attributes']['is_active'])->toBe(false);
    });

    it('can clone builder with same configuration', function () {
        $user = createUser();

        $builder = new ScheduleBuilder;
        $builder
            ->for($user)
            ->named('Original')
            ->from('2025-01-01')
            ->addPeriod('09:00', '10:00')
            ->weekly(['monday']);

        $clone = $builder->clone();
        $clone->named('Cloned');

        $original = $builder->build();
        $cloned = $clone->build();

        expect($original['attributes']['name'])->toBe('Original');
        expect($cloned['attributes']['name'])->toBe('Cloned');
        expect($original['attributes']['start_date'])->toBe($cloned['attributes']['start_date']);
        expect($original['periods'])->toEqual($cloned['periods']);
    });

    it('validates required fields', function () {
        $builder = new ScheduleBuilder;

        // Missing schedulable
        expect(fn () => $builder->from('2025-01-01')->build())
            ->toThrow(\InvalidArgumentException::class, 'Schedulable model must be set');

        // Missing start date
        $user = createUser();
        $builder->reset(); // Reset builder state
        expect(fn () => $builder->for($user)->build())
            ->toThrow(\InvalidArgumentException::class, 'Start date must be set');
    });

    it('can use between method for date range', function () {
        $user = createUser();

        $builder = new ScheduleBuilder;
        $built = $builder
            ->for($user)
            ->between('2025-01-01', '2025-12-31')
            ->build();

        expect($built['attributes']['start_date'])->toBe('2025-01-01');
        expect($built['attributes']['end_date'])->toBe('2025-12-31');
    });

    it('can use forYear method to set date range for a year', function () {
        $user = createUser();

        $builder = new ScheduleBuilder;
        $built = $builder
            ->for($user)
            ->forYear(2025)
            ->build();

        expect($built['attributes']['start_date'])->toBe('2025-01-01');
        expect($built['attributes']['end_date'])->toBe('2025-12-31');
    });

    it('can use forYear with different years', function () {
        $user = createUser();

        $builder = new ScheduleBuilder;

        // Test with 2024
        $built2024 = $builder
            ->for($user)
            ->forYear(2024)
            ->build();

        expect($built2024['attributes']['start_date'])->toBe('2024-01-01');
        expect($built2024['attributes']['end_date'])->toBe('2024-12-31');

        // Test with 2026
        $builder->reset();
        $built2026 = $builder
            ->for($user)
            ->forYear(2026)
            ->build();

        expect($built2026['attributes']['start_date'])->toBe('2026-01-01');
        expect($built2026['attributes']['end_date'])->toBe('2026-12-31');
    });

    it('can chain forYear with other methods', function () {
        $user = createUser();

        $builder = new ScheduleBuilder;
        $built = $builder
            ->for($user)
            ->named('Yearly Schedule')
            ->forYear(2025)
            ->addPeriod('09:00', '17:00')
            ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
            ->build();

        expect($built['attributes']['name'])->toBe('Yearly Schedule');
        expect($built['attributes']['start_date'])->toBe('2025-01-01');
        expect($built['attributes']['end_date'])->toBe('2025-12-31');
        expect($built['attributes']['is_recurring'])->toBe(true);
        expect($built['attributes']['frequency'])->toBe(Frequency::WEEKLY);
        expect($built['periods'])->toHaveCount(1);
    });

    it('provides getter methods for current state', function () {
        $user = createUser();

        $builder = new ScheduleBuilder;
        $builder
            ->for($user)
            ->named('Test')
            ->from('2025-01-01')
            ->addPeriod('09:00', '10:00')
            ->noOverlap();

        expect($builder->getAttributes())->toHaveKey('name', 'Test');
        expect($builder->getPeriods())->toHaveCount(1);
        expect($builder->getRules())->toHaveKey('no_overlap');
    });

});

describe('ScheduleBuilder Integration', function () {

    it('integrates with ScheduleService for saving', function () {
        $user = createUser();

        $schedule = (new ScheduleBuilder)
            ->for($user)
            ->named('Integration Test')
            ->from('2025-01-01')
            ->addPeriod('09:00', '10:00')
            ->save();

        expect($schedule)->toBeInstanceOf(Schedule::class);
        expect($schedule->name)->toBe('Integration Test');
    });

    it('can save schedule using forYear method', function () {
        $user = createUser();

        $schedule = (new ScheduleBuilder)
            ->for($user)
            ->named('Yearly Integration Test')
            ->forYear(2025)
            ->addPeriod('09:00', '10:00')
            ->save();

        expect($schedule)->toBeInstanceOf(Schedule::class);
        expect($schedule->name)->toBe('Yearly Integration Test');
        expect($schedule->start_date->format('Y-m-d'))->toBe('2025-01-01');
        expect($schedule->end_date->format('Y-m-d'))->toBe('2025-12-31');
    });

});
