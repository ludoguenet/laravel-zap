<?php

namespace Zap\Tests;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Zap\Models\SchedulePeriod as Model;

class ZapTestUuidSchedulePeriod extends Model
{
    use HasUuids;
}
