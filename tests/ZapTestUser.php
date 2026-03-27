<?php

namespace Zap\Tests;

use Illuminate\Database\Eloquent\Model;
use Zap\Models\Concerns\HasSchedules;

class ZapTestUser extends Model
{
    use HasSchedules;

    protected $table = 'zap_test_users';

    protected $fillable = ['name', 'email'];
}
