<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TgUser extends Model
{
    protected $table = 'tg_users';

    protected $fillable = [
        'telegram_id',
        'username',
        'locale',
    ];

    protected $casts = [
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'tg_user_id');
    }

    /** Quick scope by Telegram ID */
    public function scopeByTelegramId($q, string|int $telegramId)
    {
        return $q->where('telegram_id', (string)$telegramId);
    }
}
