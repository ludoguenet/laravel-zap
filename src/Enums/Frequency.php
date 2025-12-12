<?php

namespace Zap\Enums;

use Carbon\CarbonInterface;
use Zap\Data\FrequencyConfig;

enum Frequency: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case WEEKLY_ODD = 'weekly_odd';
    case WEEKLY_EVEN = 'weekly_even';
    case BIWEEKLY = 'biweekly';
    case MONTHLY = 'monthly';
    case BIMONTHLY = 'bimonthly';
    case QUARTERLY = 'quarterly';
    case SEMIANNUALLY = 'semiannually';
    case ANNUALLY = 'annually';

    public function getNextRecurrence(CarbonInterface $current): CarbonInterface
    {
        return match ($this) {
            self::DAILY => $current->copy()->addDay(),
            self::WEEKLY => $current->copy()->addWeek(),
            self::WEEKLY_ODD => \Zap\Helper\DateHelper::nextWeekOdd($current),
            self::WEEKLY_EVEN => \Zap\Helper\DateHelper::nextWeekEven($current),
            self::BIWEEKLY => $current->copy()->addWeeks(2),
            self::MONTHLY => $current->copy()->addMonth(),
            self::BIMONTHLY => $current->copy()->addMonths(2),
            self::QUARTERLY => $current->copy()->addMonths(3),
            self::SEMIANNUALLY => $current->copy()->addMonths(6),
            self::ANNUALLY => $current->copy()->addYear(),
        };
    }

    /**
     * @return class-string<FrequencyConfig>
     */
    public function configClass(): string
    {
        return match ($this) {
            self::DAILY => \Zap\Data\DailyFrequencyConfig::class,
            self::WEEKLY => \Zap\Data\WeeklyFrequencyConfig::class,
            self::WEEKLY_ODD => \Zap\Data\WeeklyEvenOddFrequencyConfig\WeeklyOddFrequencyConfig::class,
            self::WEEKLY_EVEN => \Zap\Data\WeeklyEvenOddFrequencyConfig\WeeklyEvenFrequencyConfig::class,
            self::BIWEEKLY => \Zap\Data\BiWeeklyFrequencyConfig::class,
            self::MONTHLY => \Zap\Data\MonthlyFrequencyConfig::class,
            self::BIMONTHLY => \Zap\Data\BiMonthlyFrequencyConfig::class,
            self::QUARTERLY => \Zap\Data\QuarterlyFrequencyConfig::class,
            self::SEMIANNUALLY => \Zap\Data\SemiAnnuallyFrequencyConfig::class,
            self::ANNUALLY => \Zap\Data\AnnuallyFrequencyConfig::class,
        };
    }

    public static function weeklyFrequencies(): array
    {
        return [
            self::WEEKLY,
            self::WEEKLY_ODD,
            self::WEEKLY_EVEN,
        ];
    }

    public static function filteredByWeekday(): array
    {
        return [
            self::WEEKLY,
            self::BIWEEKLY,
        ];
    }

    public static function filteredByDaysOfMonth(): array
    {
        return [
            self::MONTHLY,
            self::BIMONTHLY,
            self::QUARTERLY,
            self::SEMIANNUALLY,
            self::ANNUALLY,
        ];
    }
}
