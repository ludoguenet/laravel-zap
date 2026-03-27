<?php

namespace Zap\Enums;

use Carbon\CarbonInterface;
use Zap\Data\DailyFrequencyConfig;
use Zap\Data\FrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\AnnuallyFrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\BiMonthlyFrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\MonthlyFrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\QuarterlyFrequencyConfig;
use Zap\Data\MonthlyFrequencyConfig\SemiAnnuallyFrequencyConfig;
use Zap\Data\WeeklyEvenOddFrequencyConfig\WeeklyEvenFrequencyConfig;
use Zap\Data\WeeklyEvenOddFrequencyConfig\WeeklyOddFrequencyConfig;
use Zap\Data\WeeklyFrequencyConfig\BiWeeklyFrequencyConfig;
use Zap\Data\WeeklyFrequencyConfig\WeeklyFrequencyConfig;
use Zap\Helper\DateHelper;

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
            self::WEEKLY_ODD => DateHelper::nextWeekOdd($current),
            self::WEEKLY_EVEN => DateHelper::nextWeekEven($current),
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
            self::DAILY => DailyFrequencyConfig::class,
            self::WEEKLY => WeeklyFrequencyConfig::class,
            self::WEEKLY_ODD => WeeklyOddFrequencyConfig::class,
            self::WEEKLY_EVEN => WeeklyEvenFrequencyConfig::class,
            self::BIWEEKLY => BiWeeklyFrequencyConfig::class,
            self::MONTHLY => MonthlyFrequencyConfig::class,
            self::BIMONTHLY => BiMonthlyFrequencyConfig::class,
            self::QUARTERLY => QuarterlyFrequencyConfig::class,
            self::SEMIANNUALLY => SemiAnnuallyFrequencyConfig::class,
            self::ANNUALLY => AnnuallyFrequencyConfig::class,
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
