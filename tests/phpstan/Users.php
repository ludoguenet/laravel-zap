<?php

use Illuminate\Database\Eloquent\Model;
use Zap\Models\Concerns\HasSchedules;

class Users extends Model
{
    use HasSchedules;

    protected $table = 'users';

    protected $fillable = ['name', 'email'];

    public function getKey()
    {
        return 1;
    }
}
