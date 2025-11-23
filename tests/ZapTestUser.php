<?php

namespace Zap\Tests;

use Illuminate\Database\Eloquent\Model;

class ZapTestUser extends Model
{
    use \Zap\Models\Concerns\HasSchedules;

    protected $table = 'zap_test_users';

    protected $fillable = ['name', 'email'];
}
