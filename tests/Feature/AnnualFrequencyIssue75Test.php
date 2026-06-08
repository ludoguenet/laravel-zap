<?php

/**
 * Tests for Issue #75: Annual (and sub-annual) frequency schedules match wrong months
 *
 * The forDate() query scope previously matched BIMONTHLY, QUARTERLY, SEMIANNUALLY, and ANNUALLY
 * schedules by day-of-month alone, without verifying the month. An annual block on May 8 would
 * therefore appear in queries for June 8, July 8, etc.
 *
 * @see https://github.com/ludoguenet/laravel-zap/issues/75
 */

use Zap\Facades\Zap;
use Zap\Models\Schedule;

describe('Issue #75 — Annual frequency should not block wrong months', function () {

    it('forDate returns annually scheduled on the correct month', function () {
        $user = createUser();

        Zap::for($user)
            ->blocked()
            ->annually(['days_of_month' => [8], 'start_month' => 5])
            ->from('2025-05-08')
            ->addPeriod('00:00', '23:59')
            ->save();

        $schedules = Schedule::forDate('2025-05-08')->get();

        expect($schedules)->toHaveCount(1);
    });

    it('forDate does not return annually scheduled on a different month', function () {
        $user = createUser();

        Zap::for($user)
            ->blocked()
            ->annually(['days_of_month' => [8], 'start_month' => 5])
            ->from('2025-05-08')
            ->addPeriod('00:00', '23:59')
            ->save();

        foreach (['2025-06-08', '2025-07-08', '2025-08-08', '2025-09-08', '2025-10-08', '2025-11-08', '2025-12-08', '2026-01-08', '2026-04-08'] as $wrongDate) {
            $schedules = Schedule::forDate($wrongDate)->get();
            expect($schedules)->toBeEmpty("Annual May-8 schedule should not appear on {$wrongDate}");
        }
    });

    it('forDate returns annually scheduled on the same month next year', function () {
        $user = createUser();

        Zap::for($user)
            ->blocked()
            ->annually(['days_of_month' => [8], 'start_month' => 5])
            ->from('2025-05-08')
            ->to('2027-05-08')
            ->addPeriod('00:00', '23:59')
            ->save();

        $schedules = Schedule::forDate('2026-05-08')->get();

        expect($schedules)->toHaveCount(1);
    });

    it('annual blocked schedule does not block availability on wrong months via public API', function () {
        $user = createUser();

        // Daily availability so every day has slots
        Zap::for($user)
            ->availability()
            ->daily()
            ->from('2025-01-01')
            ->to('2025-12-31')
            ->addPeriod('09:00', '17:00')
            ->save();

        // Annual block on May 8 only
        Zap::for($user)
            ->blocked()
            ->annually(['days_of_month' => [8], 'start_month' => 5])
            ->from('2025-05-08')
            ->addPeriod('09:00', '17:00')
            ->save();

        // May 8 should be fully blocked
        expect($user->isBookableAt('2025-05-08'))->toBeFalse();

        // June 8 should NOT be blocked
        expect($user->isBookableAt('2025-06-08'))->toBeTrue();

        // Other months' 8th should also not be blocked
        expect($user->isBookableAt('2025-01-08'))->toBeTrue();
        expect($user->isBookableAt('2025-12-08'))->toBeTrue();
    });

});

describe('Issue #75 — Bimonthly frequency should respect start_month', function () {

    it('forDate returns bimonthly scheduled on valid months only', function () {
        $user = createUser();

        // Bimonthly starting January: fires on Jan, Mar, May, Jul, Sep, Nov
        Zap::for($user)
            ->availability()
            ->bimonthly(['days_of_month' => [5], 'start_month' => 1])
            ->from('2025-01-05')
            ->to('2025-12-31')
            ->addPeriod('09:00', '17:00')
            ->save();

        // Valid months (same parity as start_month=1, i.e. odd months)
        foreach (['2025-01-05', '2025-03-05', '2025-05-05', '2025-07-05', '2025-09-05', '2025-11-05'] as $validDate) {
            $schedules = Schedule::forDate($validDate)->get();
            expect($schedules)->toHaveCount(1, "Bimonthly should appear on {$validDate}");
        }

        // Invalid months (even months when start_month is odd)
        foreach (['2025-02-05', '2025-04-05', '2025-06-05', '2025-08-05', '2025-10-05', '2025-12-05'] as $invalidDate) {
            $schedules = Schedule::forDate($invalidDate)->get();
            expect($schedules)->toBeEmpty("Bimonthly should NOT appear on {$invalidDate}");
        }
    });

});

describe('Issue #75 — Quarterly frequency should respect start_month', function () {

    it('forDate returns quarterly scheduled on valid months only', function () {
        $user = createUser();

        // Quarterly starting February: fires on Feb, May, Aug, Nov
        Zap::for($user)
            ->availability()
            ->quarterly(['days_of_month' => [15], 'start_month' => 2])
            ->from('2025-02-15')
            ->to('2025-12-31')
            ->addPeriod('09:00', '17:00')
            ->save();

        foreach (['2025-02-15', '2025-05-15', '2025-08-15', '2025-11-15'] as $validDate) {
            $schedules = Schedule::forDate($validDate)->get();
            expect($schedules)->toHaveCount(1, "Quarterly should appear on {$validDate}");
        }

        foreach (['2025-01-15', '2025-03-15', '2025-04-15', '2025-06-15', '2025-07-15', '2025-09-15', '2025-10-15', '2025-12-15'] as $invalidDate) {
            $schedules = Schedule::forDate($invalidDate)->get();
            expect($schedules)->toBeEmpty("Quarterly should NOT appear on {$invalidDate}");
        }
    });

});

describe('Issue #75 — Semiannually frequency should respect start_month', function () {

    it('forDate returns semiannually scheduled on valid months only', function () {
        $user = createUser();

        // Semiannually starting March: fires on Mar and Sep
        Zap::for($user)
            ->availability()
            ->semiannually(['days_of_month' => [10], 'start_month' => 3])
            ->from('2025-03-10')
            ->to('2025-12-31')
            ->addPeriod('09:00', '17:00')
            ->save();

        foreach (['2025-03-10', '2025-09-10'] as $validDate) {
            $schedules = Schedule::forDate($validDate)->get();
            expect($schedules)->toHaveCount(1, "Semiannually should appear on {$validDate}");
        }

        foreach (['2025-01-10', '2025-02-10', '2025-04-10', '2025-05-10', '2025-06-10', '2025-07-10', '2025-08-10', '2025-10-10', '2025-11-10', '2025-12-10'] as $invalidDate) {
            $schedules = Schedule::forDate($invalidDate)->get();
            expect($schedules)->toBeEmpty("Semiannually should NOT appear on {$invalidDate}");
        }
    });

});
