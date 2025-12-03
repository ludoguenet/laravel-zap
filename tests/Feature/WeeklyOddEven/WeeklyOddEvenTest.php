<?php

use Zap\Enums\ScheduleTypes;
use Zap\Exceptions\ScheduleConflictException;
use Zap\Facades\Zap;
use Zap\Models\Schedule;

describe('Weekly Odd/Even ', function () {

    it('can create an availability for even weeks', function () {
        $user = createUser();

        $availability = Zap::for($user)
            ->named('Office Hours for Even Weeks')
            ->availability()
            ->forYear(now()->addYear()->year)
            ->addPeriod('09:00', '12:00')
            ->addPeriod('14:00', '17:00')
            ->weeklyEven(['monday', 'tuesday', 'wednesday', 'friday'])
            ->save();

        expect($availability)->not->toBeNull();

        expect($availability->periods)->toHaveCount(2);

        expect($availability->periods[0]->start_time)->toBe('09:00');
        expect($availability->periods[0]->end_time)->toBe('12:00');

        expect($availability->periods[1]->start_time)->toBe('14:00');
        expect($availability->periods[1]->end_time)->toBe('17:00');

        expect($availability->frequency)->toBe('weekly_even');
        expect($availability->frequency_config)->toBe([
            'days' => ['monday', 'tuesday', 'wednesday', 'friday'],
        ]);

    });

    it('can create an availability for odd weeks', function () {
        $user = createUser();

        $availability = Zap::for($user)
            ->named('Office Hours for Odd Weeks')
            ->availability()
            ->forYear(now()->addYear()->year)
            ->addPeriod('09:00', '12:00')
            ->addPeriod('14:00', '17:00')
            ->weeklyOdd(['monday', 'wednesday'])
            ->save();

        expect($availability)->not->toBeNull();

        expect($availability->periods)->toHaveCount(2);

        expect($availability->periods[0]->start_time)->toBe('09:00');
        expect($availability->periods[0]->end_time)->toBe('12:00');

        expect($availability->periods[1]->start_time)->toBe('14:00');
        expect($availability->periods[1]->end_time)->toBe('17:00');

        expect($availability->frequency)->toBe('weekly_odd');

        expect($availability->frequency_config)->toBe([
            'days' => ['monday', 'wednesday'],
        ]);
    });

    test('getBookableSlots with weekly even and odd', function () {
        $user = createUser();

        Zap::for($user)
            ->named('Office Hours for Even Weeks')
            ->availability()
            ->forYear(2025)
            ->addPeriod('09:00', '12:00')
            ->addPeriod('14:00', '17:00')
            ->weeklyEven(['monday', 'tuesday', 'wednesday', 'friday'])
            ->save();

        //
        // 1. Date en semaine impaire → aucun slot même si jour autorisé
        //
        $slotsWeekOdd = $user->getBookableSlots(
            date: '2025-01-01', // mercredi mais semaine 1 (impaire)
            slotDuration: 60
        );
        expect($slotsWeekOdd)->toBeEmpty();

        //
        // 2. Jour non autorisé (jeudi) → aucun slot
        //
        $slotsThursday = $user->getBookableSlots(
            date: '2025-01-02', // jeudi
            slotDuration: 60
        );
        expect($slotsThursday)->toBeEmpty();

        //
        // 3. Date en semaine paire ET jour autorisé → 6 slots
        //
        $slotsWeekEven = $user->getBookableSlots(
            date: '2025-01-06', // lundi semaine 2
            slotDuration: 60
        );

        expect($slotsWeekEven)->toHaveCount(6);
        expect($slotsWeekEven)->toBe([
            ['start_time' => '09:00', 'end_time' => '10:00', 'is_available' => true, 'buffer_minutes' => 0],
            ['start_time' => '10:00', 'end_time' => '11:00', 'is_available' => true, 'buffer_minutes' => 0],
            ['start_time' => '11:00', 'end_time' => '12:00', 'is_available' => true, 'buffer_minutes' => 0],
            ['start_time' => '14:00', 'end_time' => '15:00', 'is_available' => true, 'buffer_minutes' => 0],
            ['start_time' => '15:00', 'end_time' => '16:00', 'is_available' => true, 'buffer_minutes' => 0],
            ['start_time' => '16:00', 'end_time' => '17:00', 'is_available' => true, 'buffer_minutes' => 0],
        ]);
    });

    test('getBookableSlots with weekly odd', function () {
        $user = createUser();

        Zap::for($user)
            ->named('Office Hours for Odd Weeks')
            ->availability()
            ->forYear(2025)
            ->addPeriod('09:00', '12:00')
            ->addPeriod('14:00', '17:00')
            ->weeklyOdd(['monday', 'wednesday', 'friday'])
            ->save();

        //
        // 1. Date en semaine paire → aucun slot même si jour autorisé
        //
        $slotsWeekEven = $user->getBookableSlots(
            date: '2025-01-06', // lundi semaine 2 (paire)
            slotDuration: 60
        );
        expect($slotsWeekEven)->toBeEmpty();

        //
        // 2. Jour non autorisé en semaine impaire → aucun slot
        //
        $slotsTuesdayOdd = $user->getBookableSlots(
            date: '2025-01-07', // mardi semaine 1 (impair) mais non autorisé
            slotDuration: 60
        );
        expect($slotsTuesdayOdd)->toBeEmpty();

        //
        // 3. Date en semaine impaire et jour autorisé → 6 slots
        //
        $slotsWeekOdd = $user->getBookableSlots(
            date: '2025-01-01', // mercredi semaine 1 (impair)
            slotDuration: 60
        );

        expect($slotsWeekOdd)->toHaveCount(6);

        expect($slotsWeekOdd)->toBe([
            ['start_time' => '09:00', 'end_time' => '10:00', 'is_available' => true, 'buffer_minutes' => 0],
            ['start_time' => '10:00', 'end_time' => '11:00', 'is_available' => true, 'buffer_minutes' => 0],
            ['start_time' => '11:00', 'end_time' => '12:00', 'is_available' => true, 'buffer_minutes' => 0],
            ['start_time' => '14:00', 'end_time' => '15:00', 'is_available' => true, 'buffer_minutes' => 0],
            ['start_time' => '15:00', 'end_time' => '16:00', 'is_available' => true, 'buffer_minutes' => 0],
            ['start_time' => '16:00', 'end_time' => '17:00', 'is_available' => true, 'buffer_minutes' => 0],
        ]);
    });

    test('getNextBookableSlot returns the correct next available slot for weekly even in 2025', function () {
        $user = createUser();

        // Création d'une availability pour les semaines paires en 2025
        // Périodes : 09:00-12:00 et 14:00-17:00
        // Jours autorisés : lundi, mardi, mercredi, vendredi
        Zap::for($user)
            ->named('Office Hours for Even Weeks')
            ->availability()
            ->forYear(2025)
            ->addPeriod('09:00', '12:00')
            ->addPeriod('14:00', '17:00')
            ->weeklyEven(['monday', 'tuesday', 'wednesday', 'friday'])
            ->save();

        //
        // Test : date initiale sur un jour non autorisé → le slot doit sauter au prochain jour autorisé
        // Jeudi 2025-01-02 → semaine 1 (impaire) → pas autorisé pour weekly_even
        //
        $nextSlotThursday = $user->getNextBookableSlot(
            afterDate: '2025-01-02',
            duration: 60,
            bufferMinutes: 10
        );

        // Le prochain jour autorisé en semaine paire est lundi 2025-01-06
        expect($nextSlotThursday)->toBe([
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_available' => true,
            'buffer_minutes' => 10,
            'date' => '2025-01-06'
        ]);

        //
        // Test : date initiale sur un jour autorisé (lundi) avec assez de temps pour la durée et le buffer
        //
        $nextSlotMonday = $user->getNextBookableSlot(
            afterDate: '2025-01-06', // lundi semaine paire
            duration: 110, // 1h50
            bufferMinutes: 10
        );

        // Le slot commence à 10:00 → 11:50, toujours dans la période 09:00-12:00
        expect($nextSlotMonday)->toBe([
            'start_time' => '09:00',
            'end_time' => '10:50',
            'is_available' => true,
            'buffer_minutes' => 10,
            'date' => '2025-01-06'
        ]);

        //
        // Test : date trop tard dans la journée → le slot doit passer au prochain jour autorisé
        //
        $nextSlotLate = $user->getNextBookableSlot(
            afterDate: '2025-01-06 16:30', // lundi semaine paire
            duration: 60,
            bufferMinutes: 5
        );

        // Le dernier créneau de la journée est 16:00-17:00 → impossible pour 60 min avec buffer
        // Le slot suivant est donc le premier créneau autorisé du jour suivant en semaine paire (mardi 2025-01-07)
        expect($nextSlotLate)->toBe([
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_available' => true,
            'buffer_minutes' => 5,
            'date' => '2025-01-06',
        ]);
    });

    test('getNextBookableSlot returns the correct next available slot for weekly odd in 2025', function () {
        $user = createUser();

        // Création d'une availability pour les semaines impaires en 2025
        // Périodes : 09:00-12:00 et 14:00-17:00
        // Jours autorisés : lundi, mercredi, vendredi
        Zap::for($user)
            ->named('Office Hours for Odd Weeks')
            ->availability()
            ->forYear(2025)
            ->addPeriod('09:00', '12:00')
            ->addPeriod('14:00', '17:00')
            ->weeklyOdd(['monday', 'wednesday', 'friday'])
            ->save();

        //
        // Test : date initiale sur un jour en semaine paire → aucun slot disponible
        // Mardi 2025-01-07 → semaine 2 (paire)
        //
        $nextSlotEvenWeek = $user->getNextBookableSlot(
            afterDate: '2025-01-07',
            duration: 60,
            bufferMinutes: 10
        );

        // Le prochain jour disponible en semaine impaire est mercredi 2025-01-01
        expect($nextSlotEvenWeek)->toBe([
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_available' => true,
            'buffer_minutes' => 10,
            'date' => '2025-01-13',
        ]);

        //
        // Test : date initiale sur un jour autorisé (mercredi semaine impaire)
        //
        $nextSlotWednesday = $user->getNextBookableSlot(
            afterDate: '2025-01-01', // mercredi semaine 1 (impair)
            duration: 110, // 1h50
            bufferMinutes: 10
        );

        // Le slot commence à 09:00 → 10:50, toujours dans la période 09:00-12:00
        expect($nextSlotWednesday)->toBe([
            'start_time' => '09:00',
            'end_time' => '10:50',
            'is_available' => true,
            'buffer_minutes' => 10,
            'date' => '2025-01-01',
        ]);

        //
        // Test : date trop tard dans la journée → le slot doit passer au prochain jour autorisé
        //
        $nextSlotLate = $user->getNextBookableSlot(
            afterDate: '2025-01-01', // mercredi semaine impaire
            duration: 60,
            bufferMinutes: 5
        );

        // Le dernier créneau de la journée est 16:00-17:00 → impossible pour 60 min avec buffer
        // Le slot suivant est donc le premier créneau autorisé du jour suivant en semaine impaire (vendredi 2025-01-03)
        expect($nextSlotLate)->toBe([
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_available' => true,
            'buffer_minutes' => 5,
            'date' => '2025-01-01',
        ]);
    });

    test('isAvailableAt correctly checks user appointment for weekly even in 2025', function () {
        $user = createUser();

        // Création d'un appointment pour les semaines paires
        // Périodes : 09:00-12:00 et 14:00-17:00
        // Jours autorisés : lundi, mardi, mercredi
        Zap::for($user)
            ->named('Appointment for Even Weeks')
            ->appointment()
            ->forYear(2025)
            ->addPeriod('09:00', '12:00')
            ->addPeriod('14:00', '17:00')
            ->weeklyEven(['monday', 'tuesday', 'wednesday'])
            ->save();

        //
        // Disponible car semaine impaire
        //
        $available = $user->isAvailableAt('2025-01-01', '09:00', '09:30');
        expect($available)->toBeTrue();

        //
        // Non disponible car semaine paire
        //
        $notAvailableDay = $user->isAvailableAt('2025-01-06', '09:00', '09:30');
        expect($notAvailableDay)->toBeFalse();

        //
        // Disponible car vendredi semaine paire
        //
        $available = $user->isAvailableAt('2025-01-10', '09:00', '09:30');
        expect($available)->toBeTrue();


    });

    test('isAvailableAt correctly checks user appointment for weekly odd in 2025', function () {
        $user = createUser();

        // Création d'un appointment pour les semaines impaires
        // Périodes : 09:00-12:00 et 14:00-17:00
        // Jours autorisés : lundi, mardi, mercredi
        Zap::for($user)
            ->named('Appointment for Odd Weeks')
            ->appointment()
            ->forYear(2025)
            ->addPeriod('09:00', '12:00')
            ->addPeriod('14:00', '17:00')
            ->weeklyOdd(['monday', 'tuesday', 'wednesday'])
            ->save();

        //
        // Non disponible car mercredi semaine impaire
        //
        $available = $user->isAvailableAt('2025-01-01', '09:00', '09:30');
        expect($available)->toBeFalse();

        //
        // Disponible car semaine paire
        //
        $notAvailableDay = $user->isAvailableAt('2025-01-06', '09:00', '09:30');
        expect($notAvailableDay)->toBeTrue();

        //
        // Disponible car vendredi semaine impaire
        //
        $available = $user->isAvailableAt('2025-01-03', '09:00', '09:30');
        expect($available)->toBeTrue();

    });



});