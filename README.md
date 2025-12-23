<div align="center">

<img src="art/logo.png?v=2" alt="Zap Logo" width="200">

**Flexible schedule management for modern Laravel applications**

[![PHP Version](https://img.shields.io/badge/PHP-%E2%89%A48.5-777BB4?style=for-the-badge&logo=php)](https://www.php.net/)
[![Laravel Version](https://img.shields.io/badge/Laravel-%E2%89%A412.0-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![License](http://poser.pugx.org/laraveljutsu/zap/license?style=for-the-badge)](https://packagist.org/packages/laraveljutsu/zap)
[![Total Downloads](http://poser.pugx.org/laraveljutsu/zap/downloads?style=for-the-badge)](https://packagist.org/packages/laraveljutsu/zap)
[![Why PHP](https://img.shields.io/badge/Why_PHP-in_2026-7A86E8?style=for-the-badge&labelColor=18181b)](https://whyphp.dev)

[Website](https://ludovicguenet.dev) • [Documentation](https://laravel-zap.com) • [Support](mailto:ludo@epekta.com)

</div>

---

## 🎯 What is Zap?

Zap is a comprehensive calendar and scheduling system for Laravel. Manage availabilities, appointments, blocked times, and custom schedules for any resource—doctors, meeting rooms, employees, and more.

**Perfect for:**
- 📅 Appointment booking systems
- 🏥 Healthcare resource management
- 👔 Employee shift scheduling
- 🏢 Shared office space bookings

---

## 📦 Installation

**Requirements:** PHP ≤8.5 • Laravel ≤12.0

```bash
composer require laraveljutsu/zap
php artisan vendor:publish --tag=zap-migrations
php artisan migrate
```

### Setup Your Models

Add the `HasSchedules` trait to any Eloquent model you want to make schedulable:

```php
use Zap\Models\Concerns\HasSchedules;

class Doctor extends Model
{
    use HasSchedules;
}
```

### Note for Apps Using UUIDs/ULIDs/GUIDs

This package expects the primary key of your models to be an auto-incrementing int. If it is not, you may need to modify the `create_schedules_table` and `create_schedule_periods_table` migration and/or modify the default configuration. See [Custom Model Support](#custom-model-support) for more information.


### Before Running Migrations

**If you are USING UUIDs**, see the [Custom Model Support](#custom-model-support) section of the docs on UUID steps, before you continue. It explains some changes you may want to make to the migrations and config file before continuing. It also mentions important considerations after extending this package's models for UUID capability.

---

## 🧩 Core Concepts

Zap uses four schedule types to model different scenarios:

| Type | Purpose | Overlap Behavior |
|------|---------|------------------|
| **Availability** | Define when resources can be booked | ✅ Allows overlaps |
| **Appointment** | Actual bookings or scheduled events | ❌ Prevents overlaps |
| **Blocked** | Periods where booking is forbidden | ❌ Prevents overlaps |
| **Custom** | Neutral schedules with explicit rules | ⚙️ You define the rules |

---

## 🚀 Quick Start

Here's a complete example of setting up a doctor's schedule:

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

> 💡 **Tip:** You can also use the `zap()` helper function instead of the facade: `zap()->for($doctor)->...` (no import needed)

---

## 📅 Schedule Patterns

### Recurrence Patterns

Zap supports various recurrence patterns for flexible scheduling:

```php
// Daily
$schedule->daily()->from('2025-01-01')->to('2025-12-31');

// Weekly (specific days)
$schedule->weekly(['monday', 'wednesday', 'friday'])->forYear(2025);

// Weekly with time period (convenience method)
$schedule->weekDays(['monday', 'wednesday', 'friday'], '09:00', '17:00')->forYear(2025);

// Weekly odd (runs only on odd-numbered weeks)
$schedule->weeklyOdd(['monday', 'wednesday', 'friday'])->forYear(2025);

// Weekly odd with time period (convenience method)
$schedule->weekOddDays(['monday', 'wednesday', 'friday'], '09:00', '17:00')->forYear(2025);

// Weekly even (runs only on even-numbered weeks)
$schedule->weeklyEven(['monday', 'wednesday', 'friday'])->forYear(2025);

// Weekly even with time period (convenience method)
$schedule->weekEvenDays(['monday', 'wednesday', 'friday'], '09:00', '17:00')->forYear(2025);

// Bi-weekly (week of the start date by default, optional anchor)
$schedule->biweekly(['tuesday', 'thursday'])->from('2025-01-07')->to('2025-03-31');

// Monthly (supports multiple days)
$schedule->monthly(['days_of_month' => [1, 15]])->forYear(2025);

// Bi-monthly (multiple days, optional start_month anchor)
$schedule->bimonthly(['days_of_month' => [5, 20], 'start_month' => 2])
    ->from('2025-01-05')->to('2025-06-30');

// Quarterly (multiple days, optional start_month anchor)
$schedule->quarterly(['days_of_month' => [7, 21], 'start_month' => 2])
    ->from('2025-02-15')->to('2025-11-15');

// Semi-annually (multiple days, optional start_month anchor)
$schedule->semiannually(['days_of_month' => [10], 'start_month' => 3])
    ->from('2025-03-10')->to('2025-12-10');

// Annually (multiple days, optional start_month anchor)
$schedule->annually(['days_of_month' => [1, 15], 'start_month' => 4])
    ->from('2025-04-01')->to('2026-04-01');
```

### Date Ranges

Specify when schedules are active:

```php
$schedule->from('2025-01-15');                          // Single date
$schedule->on('2025-01-15');                            // Alias for from()
$schedule->from('2025-01-01')->to('2025-12-31');        // Date range
$schedule->between('2025-01-01', '2025-12-31');         // Alternative syntax
$schedule->forYear(2025);                               // Entire year shortcut
```

### Time Periods

Define working hours and time slots:

```php
// Single period
$schedule->addPeriod('09:00', '17:00');

// Multiple periods (split shifts)
$schedule->addPeriod('09:00', '12:00');
$schedule->addPeriod('14:00', '17:00');
```

---

## 🔍 Query & Check Availability

Check availability and query schedules:

```php
// Check if there is at least one bookable slot on the day
$isBookable = $doctor->isBookableAt('2025-01-15', 60);

// Check if a specific time range is bookable
$isBookable = $doctor->isBookableAtTime('2025-01-15', '9:00', '9:30');

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

> ⚠️ **Note:** `isAvailableAt()` is deprecated in favor of `isBookableAt()`, `isBookableAtTime()`, and `getBookableSlots()`. Use the bookable APIs for all new code.

---

## 💼 Real-World Examples

### 🏥 Doctor Appointment System

```php
// Office hours
Zap::for($doctor)
    ->named('Office Hours')
    ->availability()
    ->forYear(2025)
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->addPeriod('09:00', '12:00')
    ->addPeriod('14:00', '17:00')
    ->save();

// Lunch break
Zap::for($doctor)
    ->named('Lunch Break')
    ->blocked()
    ->forYear(2025)
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->addPeriod('12:00', '13:00')
    ->save();

// Book appointment
Zap::for($doctor)
    ->named('Patient A - Checkup')
    ->appointment()
    ->from('2025-01-15')
    ->addPeriod('10:00', '11:00')
    ->withMetadata(['patient_id' => 1])
    ->save();

// Get available slots
$slots = $doctor->getBookableSlots('2025-01-15', 60, 15);
```

### 🏢 Meeting Room Booking

```php
// Room availability (using weekDays convenience method)
Zap::for($room)
    ->named('Conference Room A')
    ->availability()
    ->weekDays(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], '08:00', '18:00')
    ->forYear(2025)
    ->save();

// Book meeting
Zap::for($room)
    ->named('Board Meeting')
    ->appointment()
    ->from('2025-03-15')
    ->addPeriod('09:00', '11:00')
    ->withMetadata(['organizer' => 'john@company.com'])
    ->save();
```

### 👔 Employee Shift Management

```php
// Regular schedule (using weekDays convenience method)
Zap::for($employee)
    ->named('Regular Shift')
    ->availability()
    ->weekDays(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], '09:00', '17:00')
    ->forYear(2025)
    ->save();

// Vacation
Zap::for($employee)
    ->named('Vacation Leave')
    ->blocked()
    ->between('2025-06-01', '2025-06-15')
    ->addPeriod('00:00', '23:59')
    ->save();
```

---

## ⚙️ Configuration

Publish the migration:

```bash
php artisan vendor:publish --tag=zap-migrations
```

Publish and customize the configuration:

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

Create custom schedules with explicit overlap rules:

```php
Zap::for($user)
    ->named('Custom Event')
    ->custom()
    ->from('2025-01-15')
    ->addPeriod('15:00', '16:00')
    ->noOverlap()  // Explicitly prevent overlaps
    ->save();
```

### Metadata Support

Attach custom metadata to schedules:

```php
->withMetadata([
    'patient_id' => 1,
    'type' => 'consultation',
    'notes' => 'Follow-up required'
])
```

### Custom Model Support

If you're using UUIDs (ULID, GUID, etc) for your `User` models or `Schedule` / `SchedulePeriod` models there are a few considerations to note.

Since each UUID implementation approach is different, some of these may or may not benefit you. As always, your implementation may vary.

We use "uuid" in the examples below. Adapt for ULID or GUID as needed.

#### Models

If you want all the schedule objects to have a UUID instead of an integer, you will need to extend the default `Schedule` and `SchedulePeriod` models into your own namespace in order to set some specific properties.

Create new models, which extend the `Zap\Models\Schedule` and `Zap\Models\SchedulePeriod` models of this package, and add Laravel's `HasUuids` trait (available since Laravel 9):

```bash
php artisan make:model Schedule
php artisan make:model SchedulePeriod
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Zap\Models\Schedule as Model;

class Schedule extends Model
{
    use HasUuids;
}
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Zap\Models\SchedulePeriod as Model;

class SchedulePeriod extends Model
{
    use HasUuids;
}
```

#### Configuration

```diff
// config/zap.php

'models' => [
-   'schedule' => \Zap\Models\Schedule::class,
+   'schedule' => \App\Models\Schedule::class,

-   'schedule_period' => \Zap\Models\SchedulePeriod::class,
+   'schedule_period' => \App\Models\SchedulePeriod::class,
],
```

#### Migrations

You will need to update the `create_schedules_table` and `create_schedule_periods_table` migration after creating it with `php artisan vendor:publish`. After making your edits, be sure to run the migration.

```diff
// database/migrations/*_create_schedules_table.php

- $table->id();
+ $table->uuid('id')->primary();
- $table->morphs('schedulable');
+ $table->uuidMorphs('schedulable');

// database/migrations/*_create_schedule_periods_table

- $table->id();
+ $table->uuid('id')->primary();
- $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
+ $table->foreignUuid('schedule_id')->constrained()->cascadeOnDelete();
```

---

## 🤝 Contributing

We welcome contributions! Follow PSR-12 coding standards and include tests.

```bash
git clone https://github.com/ludoguenet/laravel-zap.git

cd laravel-zap

composer install
composer pest
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
