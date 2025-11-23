<?php

namespace Zap\Tests;

use Illuminate\Database\Eloquent\Model;

class ZapTestRoom extends Model
{
    use \Zap\Models\Concerns\HasSchedules;

    protected $table = 'zap_test_rooms';

    protected $fillable = ['name'];
}
