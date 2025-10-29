<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderSetting extends Model
{
    protected $fillable = ['tg_user_id','company_id','enabled','time_local','days_before'];

    protected $casts = [
        'enabled' => 'bool',
        'days_before' => 'array',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}
