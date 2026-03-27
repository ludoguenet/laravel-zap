<?php

use Zap\Builders\ScheduleBuilder;
use Zap\Data\MonthlyFrequencyConfig\EveryXMonthsFrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\MonthlyOrdinalWeekdayFrequencyConfig;
use Zap\Data\WeeklyFrequencyConfig\EveryXWeeksFrequencyConfig;

describe('ScheduleBuilder Dynamic Frequency Methods', function () {

    describe('Dynamic Weekly Frequencies', function () {

        it('supports everyThreeWeeks', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $built = $builder
                ->for($user)
                ->from('2025-01-06')
                ->everyThreeWeeks(['monday', 'wednesday'])
                ->build();

            expect($built['attributes']['frequency'])->toBe('every_3_weeks');
            expect($built['attributes']['frequency_config'])->toBeInstanceOf(EveryXWeeksFrequencyConfig::class);
            expect($built['attributes']['frequency_config']->getFrequencyWeeks())->toBe(3);
            expect($built['attributes']['frequency_config']->days)->toBe(['monday', 'wednesday']);
        });

        it('supports everyFourWeeks with startsOn', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $built = $builder
                ->for($user)
                ->from('2025-01-13')
                ->everyFourWeeks(['friday'], '2025-01-06')
                ->build();

            expect($built['attributes']['frequency'])->toBe('every_4_weeks');
            expect($built['attributes']['frequency_config'])->toBeInstanceOf(EveryXWeeksFrequencyConfig::class);
            expect($built['attributes']['frequency_config']->getFrequencyWeeks())->toBe(4);
            expect($built['attributes']['frequency_config']->days)->toBe(['friday']);
            expect($built['attributes']['frequency_config']->startsOn->toDateString())->toBe('2025-01-06');
        });

        it('supports everySixWeeks', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $built = $builder
                ->for($user)
                ->from('2025-01-01')
                ->everySixWeeks(['tuesday'])
                ->build();

            expect($built['attributes']['frequency'])->toBe('every_6_weeks');
            expect($built['attributes']['frequency_config'])->toBeInstanceOf(EveryXWeeksFrequencyConfig::class);
            expect($built['attributes']['frequency_config']->getFrequencyWeeks())->toBe(6);
        });

        it('supports compound numbers like everyTwentyOneWeeks', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $built = $builder
                ->for($user)
                ->from('2025-01-01')
                ->everyTwentyOneWeeks(['monday'])
                ->build();

            expect($built['attributes']['frequency'])->toBe('every_21_weeks');
            expect($built['attributes']['frequency_config'])->toBeInstanceOf(EveryXWeeksFrequencyConfig::class);
            expect($built['attributes']['frequency_config']->getFrequencyWeeks())->toBe(21);
        });

        it('supports everyFiftyTwoWeeks as the maximum', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $built = $builder
                ->for($user)
                ->from('2025-01-01')
                ->everyFiftyTwoWeeks(['monday'])
                ->build();

            expect($built['attributes']['frequency'])->toBe('every_52_weeks');
            expect($built['attributes']['frequency_config'])->toBeInstanceOf(EveryXWeeksFrequencyConfig::class);
            expect($built['attributes']['frequency_config']->getFrequencyWeeks())->toBe(52);
        });

        it('supports singular form everyThreeWeek', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $built = $builder
                ->for($user)
                ->from('2025-01-01')
                ->everyThreeWeek(['monday'])
                ->build();

            expect($built['attributes']['frequency'])->toBe('every_3_weeks');
            expect($built['attributes']['frequency_config'])->toBeInstanceOf(EveryXWeeksFrequencyConfig::class);
        });

    });

    describe('Dynamic Monthly Frequencies', function () {

        it('supports everyFourMonths', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $built = $builder
                ->for($user)
                ->from('2025-01-15')
                ->everyFourMonths(['day_of_month' => 15])
                ->build();

            expect($built['attributes']['frequency'])->toBe('every_4_months');
            expect($built['attributes']['frequency_config'])->toBeInstanceOf(EveryXMonthsFrequencyConfig::class);
            expect($built['attributes']['frequency_config']->getFrequencyMonths())->toBe(4);
            expect($built['attributes']['frequency_config']->days_of_month)->toBe([15]);
        });

        it('supports everyFiveMonths with start_month', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $built = $builder
                ->for($user)
                ->from('2025-02-10')
                ->everyFiveMonths(['days_of_month' => [10], 'start_month' => 2])
                ->build();

            expect($built['attributes']['frequency'])->toBe('every_5_months');
            expect($built['attributes']['frequency_config'])->toBeInstanceOf(EveryXMonthsFrequencyConfig::class);
            expect($built['attributes']['frequency_config']->getFrequencyMonths())->toBe(5);
            expect($built['attributes']['frequency_config']->days_of_month)->toBe([10]);
            expect($built['attributes']['frequency_config']->start_month)->toBe(2);
        });

        it('supports everySevenMonths', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $built = $builder
                ->for($user)
                ->from('2025-01-01')
                ->everySevenMonths()
                ->build();

            expect($built['attributes']['frequency'])->toBe('every_7_months');
            expect($built['attributes']['frequency_config'])->toBeInstanceOf(EveryXMonthsFrequencyConfig::class);
            expect($built['attributes']['frequency_config']->getFrequencyMonths())->toBe(7);
        });

        it('supports everyEightMonths', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $built = $builder
                ->for($user)
                ->from('2025-01-01')
                ->everyEightMonths(['day_of_month' => 20])
                ->build();

            expect($built['attributes']['frequency'])->toBe('every_8_months');
            expect($built['attributes']['frequency_config'])->toBeInstanceOf(EveryXMonthsFrequencyConfig::class);
            expect($built['attributes']['frequency_config']->getFrequencyMonths())->toBe(8);
        });

        it('supports everyNineMonths', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $built = $builder
                ->for($user)
                ->from('2025-01-01')
                ->everyNineMonths()
                ->build();

            expect($built['attributes']['frequency'])->toBe('every_9_months');
            expect($built['attributes']['frequency_config'])->toBeInstanceOf(EveryXMonthsFrequencyConfig::class);
            expect($built['attributes']['frequency_config']->getFrequencyMonths())->toBe(9);
        });

        it('supports everyTenMonths', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $built = $builder
                ->for($user)
                ->from('2025-01-01')
                ->everyTenMonths()
                ->build();

            expect($built['attributes']['frequency'])->toBe('every_10_months');
            expect($built['attributes']['frequency_config'])->toBeInstanceOf(EveryXMonthsFrequencyConfig::class);
            expect($built['attributes']['frequency_config']->getFrequencyMonths())->toBe(10);
        });

        it('supports everyElevenMonths', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $built = $builder
                ->for($user)
                ->from('2025-01-01')
                ->everyElevenMonths()
                ->build();

            expect($built['attributes']['frequency'])->toBe('every_11_months');
            expect($built['attributes']['frequency_config'])->toBeInstanceOf(EveryXMonthsFrequencyConfig::class);
            expect($built['attributes']['frequency_config']->getFrequencyMonths())->toBe(11);
        });

        it('supports singular form everyFourMonth', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $built = $builder
                ->for($user)
                ->from('2025-01-01')
                ->everyFourMonth()
                ->build();

            expect($built['attributes']['frequency'])->toBe('every_4_months');
            expect($built['attributes']['frequency_config'])->toBeInstanceOf(EveryXMonthsFrequencyConfig::class);
        });

    });

    describe('Error Handling', function () {

        it('throws BadMethodCallException for invalid number words', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            expect(fn () => $builder->for($user)->from('2025-01-01')->everyInvalidWeeks())
                ->toThrow(BadMethodCallException::class, "Invalid number word 'Invalid'");
        });

        it('throws BadMethodCallException for non-existent methods', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            expect(fn () => $builder->for($user)->from('2025-01-01')->nonExistentMethod())
                ->toThrow(BadMethodCallException::class, 'does not exist');
        });

        it('throws BadMethodCallException for week frequency over 52', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            // FiftyThree = 53, which is > 52
            expect(fn () => $builder->for($user)->from('2025-01-01')->everyFiftyThreeWeeks())
                ->toThrow(BadMethodCallException::class, 'Week frequency must be between 1 and 52');
        });

        it('throws BadMethodCallException for month frequency over 12', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            // Thirteen = 13, which is > 12
            expect(fn () => $builder->for($user)->from('2025-01-01')->everyThirteenMonths())
                ->toThrow(BadMethodCallException::class, 'Month frequency must be between 1 and 12');
        });

    });

    describe('Monthly ordinal weekday (first/second/third/fourth/last X of month)', function () {

        it('supports firstWednesdayOfMonth', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $built = $builder
                ->for($user)
                ->from('2025-01-01')
                ->firstWednesdayOfMonth()
                ->build();

            expect($built['attributes']['frequency'])->toBe('monthly_ordinal_weekday');
            expect($built['attributes']['frequency_config'])->toBeInstanceOf(MonthlyOrdinalWeekdayFrequencyConfig::class);
            expect($built['attributes']['frequency_config']->getOrdinal())->toBe(1);
            expect($built['attributes']['frequency_config']->getDayOfWeek())->toBe(3); // Carbon WEDNESDAY
        });

        it('supports secondFridayOfMonth', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $built = $builder
                ->for($user)
                ->from('2025-01-01')
                ->secondFridayOfMonth()
                ->build();

            expect($built['attributes']['frequency'])->toBe('monthly_ordinal_weekday');
            expect($built['attributes']['frequency_config']->getOrdinal())->toBe(2);
            expect($built['attributes']['frequency_config']->getDayOfWeek())->toBe(5); // Carbon FRIDAY
        });

        it('supports lastMondayOfMonth', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $built = $builder
                ->for($user)
                ->from('2025-01-01')
                ->lastMondayOfMonth()
                ->build();

            expect($built['attributes']['frequency'])->toBe('monthly_ordinal_weekday');
            expect($built['attributes']['frequency_config']->getOrdinal())->toBe(5); // last
            expect($built['attributes']['frequency_config']->getDayOfWeek())->toBe(1); // Carbon MONDAY
        });

        it('supports all ordinal and day combinations via method name', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $ordinals = ['first', 'second', 'third', 'fourth', 'last'];
            $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

            foreach ($ordinals as $ordinal) {
                foreach ($days as $day) {
                    $method = $ordinal.ucfirst($day).'OfMonth';
                    $built = $builder->reset()->for($user)->from('2025-01-01')->$method()->build();
                    expect($built['attributes']['frequency'])->toBe('monthly_ordinal_weekday');
                    expect($built['attributes']['frequency_config'])->toBeInstanceOf(MonthlyOrdinalWeekdayFrequencyConfig::class);
                }
            }
        });

        it('can chain with addPeriod and from', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $built = $builder
                ->for($user)
                ->from('2025-01-01')
                ->to('2025-12-31')
                ->addPeriod('09:00', '10:00')
                ->firstWednesdayOfMonth()
                ->build();

            expect($built['attributes']['frequency'])->toBe('monthly_ordinal_weekday');
            expect($built['attributes']['start_date'])->toBe('2025-01-01');
            expect($built['attributes']['end_date'])->toBe('2025-12-31');
            expect($built['periods'])->toHaveCount(1);
        });

        it('includes ordinal and day_of_week in config toArray', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $built = $builder
                ->for($user)
                ->from('2025-01-01')
                ->secondFridayOfMonth()
                ->build();

            $configArray = $built['attributes']['frequency_config']->toArray();

            expect($configArray)->toHaveKey('ordinal', 2);
            expect($configArray)->toHaveKey('day_of_week', 5);
        });

    });

    describe('Config toArray', function () {

        it('includes frequencyWeeks in EveryXWeeksFrequencyConfig toArray', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $built = $builder
                ->for($user)
                ->from('2025-01-01')
                ->everyThreeWeeks(['monday'])
                ->build();

            $configArray = $built['attributes']['frequency_config']->toArray();

            expect($configArray)->toHaveKey('frequencyWeeks', 3);
            expect($configArray)->toHaveKey('days', ['monday']);
        });

        it('includes frequencyMonths in EveryXMonthsFrequencyConfig toArray', function () {
            $user = createUser();
            $builder = new ScheduleBuilder;

            $built = $builder
                ->for($user)
                ->from('2025-01-01')
                ->everyFourMonths(['day_of_month' => 15])
                ->build();

            $configArray = $built['attributes']['frequency_config']->toArray();

            expect($configArray)->toHaveKey('frequencyMonths', 4);
            expect($configArray)->toHaveKey('days_of_month', [15]);
        });

    });

});
