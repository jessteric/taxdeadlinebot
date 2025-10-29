<?php

namespace App\Services\Telegram;

final class InlineKeyboard
{
    public static function buttons(array $rows): array
    {
        return ['inline_keyboard' => $rows];
    }

    public static function row(array $buttons): array
    {
        return $buttons;
    }

    public static function btn(string $text, string $data): array
    {
        return ['text' => $text, 'callback_data' => $data];
    }
}
