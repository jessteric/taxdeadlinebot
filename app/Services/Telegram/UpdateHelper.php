<?php

namespace App\Services\Telegram;

class UpdateHelper
{
    public static function chatId(array $u): int|string|null
    {
        return $u['message']['chat']['id']
            ?? $u['callback_query']['message']['chat']['id']
            ?? null;
    }

    public static function text(array $u): string
    {
        return $u['message']['text'] ?? '';
    }

    public static function username(array $u): ?string
    {
        return $u['message']['from']['username']
            ?? $u['callback_query']['from']['username']
            ?? null;
    }

    public static function locale(array $u): ?string
    {
        return $u['message']['from']['language_code']
            ?? $u['callback_query']['from']['language_code']
            ?? null;
    }
}
