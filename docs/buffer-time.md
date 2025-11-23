# Buffer Time in Laravel Zap

Buffer time adds configurable gaps between bookable slots to accommodate setup time, cleanup, or prevent back-to-back appointments.

## Quick Start

### Configuration

Set default buffer time in `config/zap.php`:

```php
'time_slots' => [
    'buffer_minutes' => 10, // 10 minutes between appointments
    // ... other settings
],
```

### Usage

```php
// Use global buffer time (from config)
$slots = $doctor->getBookableSlots('2025-03-15', 60);

// Override with specific buffer time
$slots = $doctor->getBookableSlots('2025-03-15', 60, 15);

// Explicitly disable buffer
$slots = $doctor->getBookableSlots('2025-03-15', 60, 0);
```

## How It Works

Bookable slots respect availability schedules, existing appointments, schedule blocks, and buffer time. With 60-minute appointments and 15-minute buffer:
- **9:00-10:00** (Appointment 1)
- **10:15-11:15** (Appointment 2) ← 15-minute gap
- **11:30-12:30** (Appointment 3) ← 15-minute gap

## API Methods

### getBookableSlots()

```php
$slots = $doctor->getBookableSlots(
    date: '2025-03-15',
    slotDuration: 60,
    bufferMinutes: 15
);

// Response includes buffer_minutes field
[
    [
        'start_time' => '09:00',
        'end_time' => '10:00',
        'is_available' => true,
        'buffer_minutes' => 15
    ],
    // ...
]
```

### getNextBookableSlot()

```php
$nextSlot = $doctor->getNextBookableSlot(
    afterDate: '2025-03-15',
    duration: 90,
    bufferMinutes: 10
);
```

## Examples

### Healthcare System

```php
// First, create availability schedules
$availability = Zap::for($doctor)
    ->named('Office Hours')
    ->availability()
    ->from('2025-03-15')
    ->to('2025-03-31')
    ->addPeriod('09:00', '17:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->save();

// Then retrieve bookable slots with different buffer times
$consultationSlots = $doctor->getBookableSlots('2025-03-15', 30, 10);
$surgerySlots = $surgeon->getBookableSlots('2025-03-15', 120, 30);
```

### Different Appointment Types

```php
// Create availability schedule for a doctor
$availability = Zap::for($doctor)
    ->named('Office Hours')
    ->availability()
    ->from('2025-03-15')
    ->to('2025-03-31')
    ->addPeriod('09:00', '12:00')
    ->addPeriod('14:00', '17:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->save();

// Retrieve bookable slots with different buffer times for different appointment types
// Short consultations need less buffer time
$consultationSlots = $doctor->getBookableSlots('2025-03-15', 30, 10);

// Longer procedures need more buffer time for preparation
$procedureSlots = $doctor->getBookableSlots('2025-03-15', 60, 20);
```

## Parameter Precedence

1. **Explicit parameter** (highest priority)
2. **Config value** (when parameter is `null`)
3. **Zero** (when no config set)

## Edge Cases

- **Negative values**: Automatically converted to 0
- **Large buffers**: May reduce number of bookable slots
- **Buffer > slot duration**: Perfectly valid (e.g., 30min slots with 45min buffer)

## Best Practices

1. **Match your use case**: Medical = longer buffers, quick calls = shorter buffers
2. **Test availability**: Ensure buffer doesn't over-reduce bookable slots
3. **Document clearly**: Make buffer time visible to users
4. **Consider peak times**: Different buffers for busy vs quiet periods