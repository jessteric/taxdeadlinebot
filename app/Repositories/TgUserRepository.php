<?php

namespace App\Repositories;

use App\Models\TgUser;
use App\Repositories\Contracts\TgUserRepositoryInterface;

class TgUserRepository implements TgUserRepositoryInterface
{
    public function findByTelegramId(string|int $telegramId): ?TgUser
    {
        return TgUser::query()->byTelegramId($telegramId)->first();
    }

    public function upsertFromTelegram(string|int $telegramId, ?string $username, ?string $locale = null): TgUser
    {
        return TgUser::updateOrCreate(
            ['telegram_id' => (string)$telegramId],
            ['username' => $username, 'locale' => $locale ?? 'en']
        );
    }
}
