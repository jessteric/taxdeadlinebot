<?php

namespace App\Repositories\Contracts;

use App\Models\TgUser;

interface TgUserRepositoryInterface
{
    public function findByTelegramId(string|int $telegramId): ?TgUser;
    public function upsertFromTelegram(string|int $telegramId, ?string $username, ?string $locale = null): TgUser;
}
