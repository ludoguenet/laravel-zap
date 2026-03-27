<?php

namespace Zap\Tests;

use Illuminate\Database\Eloquent\Model;
use Zap\Models\Concerns\HasSchedules;

class ZapTestRoom extends Model
{
    use HasSchedules;

    protected $table = 'zap_test_rooms';

    protected $fillable = ['name'];
}
