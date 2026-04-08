<?php

use Carbon\Carbon;
use Zap\Data\WeeklyFrequencyConfig\BiWeeklyFrequencyConfig;
use Zap\Data\WeeklyFrequencyConfig\EveryXWeeksFrequencyConfig;

/**
 * These tests verify that PHP 8.1+ deprecation warnings for implicit float to int
 * conversions do not occur when using diffInWeeks() with the modulo operator.
 *
 * @see https://wiki.php.net/rfc/implicit-float-int-deprecate
 * @see https://php.watch/versions/8.1/deprecate-implicit-conversion-incompatible-float-string
 */
describe('PHP 8.1+ Implicit Float to Int Conversion', function () {

    it('should NOT emit deprecation warnings with bi-weekly frequency checks', function () {
        $deprecationWarnings = [];
        
        set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$deprecationWarnings) {
            if ($errno === E_DEPRECATED && str_contains($errstr, 'Implicit conversion from float')) {
                $deprecationWarnings[] = [
                    'message' => $errstr,
                    'file' => $errfile,
                    'line' => $errline,
                ];
            }
            // Return false to let PHP's internal error handler run as well
            return false;
        });

        try {
            $config = new BiWeeklyFrequencyConfig(
                days: ['monday', 'wednesday'],
                startsOn: Carbon::parse('2025-03-10')
            );

            // These dates produce non-integer week differences:
            // 10 days = 1.4285714285714286 weeks
            // 11 days = 1.5714285714285714 weeks
            // 15 days = 2.1428571428571428 weeks
            // 16 days = 2.2857142857142856 weeks
            $testDates = [
                Carbon::parse('2025-03-20'),
                Carbon::parse('2025-03-21'),
                Carbon::parse('2025-03-25'),
                Carbon::parse('2025-03-26'),
            ];

            foreach ($testDates as $date) {
                $config->shouldCreateInstance($date);
            }
        } finally {
            restore_error_handler();
        }

        // If this fails, it means implicit float to int warnings are being emitted
        expect($deprecationWarnings)->toBeEmpty(
            'Detected implicit float to int conversion warnings. Details: ' . 
            json_encode($deprecationWarnings, JSON_PRETTY_PRINT)
        );
    });

    it('should NOT emit deprecation warnings with every-X-weeks frequency checks', function () {
        $deprecationWarnings = [];
        
        set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$deprecationWarnings) {
            if ($errno === E_DEPRECATED && str_contains($errstr, 'Implicit conversion from float')) {
                $deprecationWarnings[] = [
                    'message' => $errstr,
                    'file' => $errfile,
                    'line' => $errline,
                ];
            }
            return false;
        });

        try {
            $config = new EveryXWeeksFrequencyConfig(
                frequencyWeeks: 3,
                days: ['tuesday', 'thursday'],
                startsOn: Carbon::parse('2025-03-10')
            );

            $testDates = [
                Carbon::parse('2025-03-20'), // 10 days = 1.4285714285714286 weeks
                Carbon::parse('2025-03-27'), // 17 days = 2.4285714285714284 weeks
                Carbon::parse('2025-04-03'), // 24 days = 3.4285714285714284 weeks
            ];

            foreach ($testDates as $date) {
                $config->shouldCreateInstance($date);
            }
        } finally {
            restore_error_handler();
        }

        expect($deprecationWarnings)->toBeEmpty(
            'Detected implicit float to int conversion warnings. Details: ' . 
            json_encode($deprecationWarnings, JSON_PRETTY_PRINT)
        );
    });

    it('should not emit warnings during getNextRecurrence calculations', function () {
        $deprecationWarnings = [];
        set_error_handler(function ($errno, $errstr) use (&$deprecationWarnings) {
            if ($errno === E_DEPRECATED && str_contains($errstr, 'Implicit conversion from float')) {
                $deprecationWarnings[] = $errstr;
            }
            return true;
        });

        $config = new BiWeeklyFrequencyConfig(
            days: ['monday', 'wednesday', 'friday'],
            startsOn: Carbon::parse('2025-03-10')
        );

        $current = Carbon::parse('2025-03-20');
        
        $next = $config->getNextRecurrence($current);

        restore_error_handler();

        expect($deprecationWarnings)->toBeEmpty();
        expect($next)->toBeInstanceOf(Carbon::class);
    });
});
