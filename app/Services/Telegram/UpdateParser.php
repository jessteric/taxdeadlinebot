<?php

namespace App\Services\Telegram;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

final class UpdateParser
{
    public function parse(array $update): ParsedUpdate
    {
        $chatId = Arr::get($update, 'message.chat.id')
            ?? Arr::get($update, 'callback_query.message.chat.id')
            ?? null;

        $text = Arr::get($update, 'message.text');
        $cb   = Arr::get($update, 'callback_query.data');

        // Небольшой лог для дебага
        Log::info('TG_PARSE', [
            'chatId' => $chatId,
            'text'   => $text,
            'cb'     => $cb,
        ]);

        return new ParsedUpdate($chatId, is_string($text) ? trim($text) : null, is_string($cb) ? $cb : null, $update);
    }
}
