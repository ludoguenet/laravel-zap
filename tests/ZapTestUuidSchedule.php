<?php

namespace Zap\Tests;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Zap\Models\Schedule as Model;

class ZapTestUuidSchedule extends Model
{
    use HasUuids;
}
