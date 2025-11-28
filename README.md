<div align="center">

<img src="art/logo.png?v=2" alt="Zap Logo" width="200">

**Flexible schedule management for modern Laravel applications**

[![PHP Version Require](http://poser.pugx.org/laraveljutsu/zap/require/php)](https://packagist.org/packages/laraveljutsu/zap)
[![PHP Version](https://img.shields.io/badge/PHP-%E2%89%A48.5-777BB4?style=flat&logo=php)](https://www.php.net/)
[![Laravel Version](https://img.shields.io/badge/Laravel-%E2%89%A412.0-FF2D20?style=flat&logo=laravel)](https://laravel.com)
[![License](http://poser.pugx.org/laraveljutsu/zap/license)](https://packagist.org/packages/laraveljutsu/zap)
[![Total Downloads](http://poser.pugx.org/laraveljutsu/zap/downloads)](https://packagist.org/packages/laraveljutsu/zap)

[Website](https://ludovicguenet.dev) • [Documentation](https://laravel-zap.com) • [Support](mailto:ludo@epekta.com)

</div>

---

## 🎯 What is Zap?

A comprehensive calendar and scheduling system for Laravel. Manage availabilities, appointments, blocked times, and custom schedules for any resource—doctors, meeting rooms, employees, and more.

**Perfect for:** appointment booking systems • resource scheduling • shift management • calendar applications

---

## 📦 Installation

**Requirements:** PHP ≤8.5 • Laravel ≤12.0

```bash
composer require laraveljutsu/zap
php artisan vendor:publish --tag=zap-migrations
php artisan migrate
```

Add the trait to your schedulable models:

```php
use Zap\Models\Concerns\HasSchedules;

class Doctor extends Model
{
    use HasSchedules;
}
```

---

## 🧩 Core Concepts

| Type | Purpose | Overlap Behavior |
|------|---------|------------------|
| **Availability** | Define when resources can be booked | ✅ Allows overlaps |
| **Appointment** | Actual bookings or scheduled events | ❌ Prevents overlaps |
| **Blocked** | Periods where booking is forbidden | ❌ Prevents overlaps |
| **Custom** | Neutral schedules with explicit rules | ⚙️ You define the rules |

---

## 🚀 Quick Start

```php
use Zap\Facades\Zap;

// 1️⃣ Define working hours
Zap::for($doctor)
    ->named('Office Hours')
    ->availability()
    ->forYear(2025)
    ->addPeriod('09:00', '12:00')
    ->addPeriod('14:00', '17:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->save();

// 2️⃣ Block lunch break
Zap::for($doctor)
    ->named('Lunch Break')
    ->blocked()
    ->forYear(2025)
    ->addPeriod('12:00', '13:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->save();

// 3️⃣ Create an appointment
Zap::for($doctor)
    ->named('Patient A - Consultation')
    ->appointment()
    ->from('2025-01-15')
    ->addPeriod('10:00', '11:00')
    ->withMetadata(['patient_id' => 1, 'type' => 'consultation'])
    ->save();

// 4️⃣ Get bookable slots (60 min slots, 15 min buffer)
$slots = $doctor->getBookableSlots('2025-01-15', 60, 15);
// Returns: [['start_time' => '09:00', 'end_time' => '10:00', 'is_available' => true, ...], ...]

// 5️⃣ Find next available slot
$nextSlot = $doctor->getNextBookableSlot('2025-01-15', 60, 15);
```

---

## 📅 Schedule Patterns

### Recurrence

```php
// Daily
$schedule->daily()->from('2025-01-01')->to('2025-12-31');

// Weekly (specific days)
$schedule->weekly(['monday', 'wednesday', 'friday'])->forYear(2025);

// Monthly
$schedule->monthly(['day_of_month' => 1])->forYear(2025);
```

### Date Ranges

```php
$schedule->from('2025-01-15');                          // Single date
$schedule->from('2025-01-01')->to('2025-12-31');        // Date range
$schedule->between('2025-01-01', '2025-12-31');         // Alternative syntax
$schedule->forYear(2025);                               // Entire year shortcut
```

### Time Periods

```php
// Single period
$schedule->addPeriod('09:00', '17:00');

// Multiple periods (split shifts)
$schedule->addPeriod('09:00', '12:00');
$schedule->addPeriod('14:00', '17:00');
```

---

## 🔍 Query & Check

```php
// Check if there is at least one bookable slot on the day
$isBookable = $doctor->isBookableAt('2025-01-15', 60);

// Get bookable slots
$slots = $doctor->getBookableSlots('2025-01-15', 60, 15);

// Find conflicts
$conflicts = Zap::findConflicts($schedule);
$hasConflicts = Zap::hasConflicts($schedule);

// Query schedules
$doctor->schedulesForDate('2025-01-15')->get();
$doctor->schedulesForDateRange('2025-01-01', '2025-01-31')->get();

// Filter by type
$doctor->appointmentSchedules()->get();
$doctor->availabilitySchedules()->get();
$doctor->blockedSchedules()->get();

// Check schedule type
$schedule->isAvailability();
$schedule->isAppointment();
$schedule->isBlocked();
```

> `isAvailableAt()` is deprecated in favor of `isBookableAt()` and `getBookableSlots()`. Use the bookable APIs for all new code.

---

## 💼 Real-World Examples

### 🏥 Doctor Appointment System

```php
// Office hours
Zap::for($doctor)->named('Office Hours')->availability()
    ->forYear(2025)->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->addPeriod('09:00', '12:00')->addPeriod('14:00', '17:00')->save();

// Lunch break
Zap::for($doctor)->named('Lunch Break')->blocked()
    ->forYear(2025)->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->addPeriod('12:00', '13:00')->save();

// Book appointment
Zap::for($doctor)->named('Patient A - Checkup')->appointment()
    ->from('2025-01-15')->addPeriod('10:00', '11:00')
    ->withMetadata(['patient_id' => 1])->save();

// Get available slots
$slots = $doctor->getBookableSlots('2025-01-15', 60, 15);
```

### 🏢 Meeting Room Booking

```php
// Room availability
Zap::for($room)->named('Conference Room A')->availability()
    ->forYear(2025)->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->addPeriod('08:00', '18:00')->save();

// Book meeting
Zap::for($room)->named('Board Meeting')->appointment()
    ->from('2025-03-15')->addPeriod('09:00', '11:00')
    ->withMetadata(['organizer' => 'john@company.com'])->save();
```

### 👔 Employee Shift Management

```php
// Regular schedule
Zap::for($employee)->named('Regular Shift')->availability()
    ->forYear(2025)->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->addPeriod('09:00', '17:00')->save();

// Vacation
Zap::for($employee)->named('Vacation Leave')->blocked()
    ->between('2025-06-01', '2025-06-15')
    ->addPeriod('00:00', '23:59')->save();
```

---

## ⚙️ Configuration

Publish and customize:

```bash
php artisan vendor:publish --tag=zap-config
```

Key settings in `config/zap.php`:

```php
'time_slots' => [
    'buffer_minutes' => 0,  // Default buffer between slots
],

'default_rules' => [
    'no_overlap' => [
        'enabled' => true,
        'applies_to' => ['appointment', 'blocked'],
    ],
],
```

---

## 🛡️ Advanced Features

### Custom Schedules with Explicit Rules

```php
Zap::for($user)->named('Custom Event')->custom()
    ->from('2025-01-15')->addPeriod('15:00', '16:00')
    ->noOverlap()  // Explicitly prevent overlaps
    ->save();
```

### Metadata Support

```php
->withMetadata([
    'patient_id' => 1,
    'type' => 'consultation',
    'notes' => 'Follow-up required'
])
```

---

## 🤝 Contributing

We welcome contributions! Follow PSR-12 coding standards and include tests.

```bash
git clone https://github.com/laraveljutsu/zap.git
cd zap
composer install
vendor/bin/pest
```

---

## 📄 License

Open-source software licensed under the [MIT License](LICENSE).

## 🔒 Security

Report vulnerabilities to **ludo@epekta.com** (please don't use the issue tracker).

---

<div align="center">

**Made with 💛 by [Ludovic Guénet](https://www.ludovicguenet.dev) for the Laravel community**

</div>
