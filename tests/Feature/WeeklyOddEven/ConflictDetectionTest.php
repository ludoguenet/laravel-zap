<?php

use Zap\Enums\ScheduleTypes;
use Zap\Exceptions\ScheduleConflictException;
use Zap\Facades\Zap;
use Zap\Models\Schedule;

describe('Weekly Odd/Even - Conflict Detection ', function () {

    it('ensures a weekly-even event does not conflict with a non-even-week event', function () {
        $user = createUser();

        $schedule1 = Zap::for($user)
            ->named('Weekly-Even Appointment')
            ->appointment()
            ->from('2025-01-01')
            ->to('2025-12-01')
            ->addPeriod('09:00', '10:00')
            ->weeklyEven(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
            ->save();

        $schedule2 = Zap::for($user)
            ->named('Non-Even Week Appointment')
            ->appointment()
            ->from('2025-01-01')
            ->addPeriod('09:00', '10:30')
            ->save();

        expect($schedule1)->toBeInstanceOf(\Zap\Models\Schedule::class)
            ->and($schedule2)->toBeInstanceOf(\Zap\Models\Schedule::class);

    });

    it('ensures a weekly-odd event does not conflict with a non-odd-week event', function () {
        $user = createUser();

        $schedule1 = Zap::for($user)
            ->named('Weekly-Odd Appointment')
            ->appointment()
            ->from('2025-01-01')
            ->to('2025-12-01')
            ->addPeriod('09:00', '10:00')
            ->weeklyOdd(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
            ->save();

        $schedule2 = Zap::for($user)
            ->named('Non-Odd Week Appointment')
            ->appointment()
            ->from('2025-01-06')
            ->addPeriod('09:00', '10:30')
            ->save();

        expect($schedule1)->toBeInstanceOf(\Zap\Models\Schedule::class)
            ->and($schedule2)->toBeInstanceOf(\Zap\Models\Schedule::class);

    });

    it('weekly odd - detects overlapping time periods on same date', function () {
        $user = createUser();

        Zap::for($user)
            ->named('Weekly-Odd Appointment')
            ->appointment()
            ->from('2025-01-01')
            ->to('2025-12-01')
            ->addPeriod('09:00', '10:00')
            ->weeklyOdd(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
            ->save();

        // This should conflict
        expect(function () use ($user) {
            Zap::for($user)
                ->appointment()
                ->from('2025-01-01')
                ->addPeriod('09:00', '10:30')
                ->noOverlap()
                ->save();
        })->toThrow(ScheduleConflictException::class);

    });

    it('weekly even - detects overlapping time periods on same date', function () {
        $user = createUser();

        Zap::for($user)
            ->appointment()
            ->from('2025-01-01')
            ->to('2025-12-01')
            ->addPeriod('09:00', '10:00')
            ->weeklyEven(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
            ->save();

        // This should conflict
        expect(function () use ($user) {
            Zap::for($user)
                ->appointment()
                ->from('2025-01-06')
                ->addPeriod('09:00', '10:30')
                ->noOverlap()
                ->save();
        })->toThrow(ScheduleConflictException::class);

    });

    it('weekly even - finds all conflicting schedules', function () {
        $user = createUser();

        Zap::for($user)
            ->named('Weekly-Even Meeting')
            ->appointment()
            ->from('2025-01-01')
            ->to('2025-12-01')
            ->addPeriod('09:00', '10:00')
            ->weeklyEven(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
            ->save();

        Zap::for($user)
            ->named('Meeting 2')
            ->appointment()
            ->from('2025-01-01')
            ->addPeriod('10:30', '11:30')
            ->save();


        // Create a new appointment schedule that overlaps only Meeting 2.
        // Because 'Weekly-Even Meeting' is in even weeks
        $newSchedule = new Schedule([
            'schedulable_type' => get_class($user),
            'schedulable_id' => $user->getKey(),
            'start_date' => '2025-01-01',
            'name' => 'Conflicting Meeting',
            'schedule_type' => ScheduleTypes::APPOINTMENT,
        ]);

        // Add periods that overlaps only Meeting 2.
        $newSchedule->setRelation('periods', collect([
            new \Zap\Models\SchedulePeriod([
                'date' => '2025-01-01',
                'start_time' => '09:30', // Not Overlaps (09:00-10:00) because is in Even Weekly
                'end_time' => '11:00',   // Overlaps with Meeting 2 (10:30-11:30)
            ]),
        ]));

        $conflicts = Zap::findConflicts($newSchedule);

        expect($conflicts)->toHaveCount(1)
            ->and($conflicts[0]->name)->toBe('Meeting 2');;
    });

    it('weekly odd - finds all conflicting schedules', function () {
        $user = createUser();

        Zap::for($user)
            ->named('Weekly-Odd Meeting')
            ->appointment()
            ->from('2025-01-01')
            ->to('2025-12-01')
            ->addPeriod('09:00', '10:00')
            ->weeklyOdd(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
            ->save();

        Zap::for($user)
            ->named('Meeting 2')
            ->appointment()
            ->from('2025-01-06')
            ->addPeriod('10:30', '11:30')
            ->save();


        // Create a new appointment schedule that overlaps only Meeting 2.
        // Because 'Weekly-Odd Meeting' is in odd weeks
        $newSchedule = new Schedule([
            'schedulable_type' => get_class($user),
            'schedulable_id' => $user->getKey(),
            'start_date' => '2025-01-06',
            'name' => 'Conflicting Meeting',
            'schedule_type' => ScheduleTypes::APPOINTMENT,
        ]);

        // Add periods that overlaps only Meeting 2.
        $newSchedule->setRelation('periods', collect([
            new \Zap\Models\SchedulePeriod([
                'date' => '2025-01-06',
                'start_time' => '09:30', // Not Overlaps (09:00-10:00) because is in Odd Weekly
                'end_time' => '11:00',   // Overlaps with Meeting 2 (10:30-11:30)
            ]),
        ]));

        $conflicts = Zap::findConflicts($newSchedule);

        expect($conflicts)->toHaveCount(1)
            ->and($conflicts[0]->name)->toBe('Meeting 2');;
    });



});