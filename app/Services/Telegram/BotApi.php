<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BotApi
{
    public function __construct(private string $token = '')
    {
        $this->token = $this->token ?: (string)config('telegram.token');
    }

    /**
     * Отправка сообщения с произвольными опциями.
     * @param int|string $chatId
     * @param string $text
     * @param array $params Доп. параметры Telegram API (parse_mode, reply_markup, etc.)
     * @return array
     * @throws ConnectionException
     */
    public function sendMessage(int|string $chatId, string $text, array $params = []): array
    {
        $payload = array_merge([
            'chat_id' => (string)$chatId,
            'text'    => $text,
        ], $params);

        // если reply_markup передан как массив — превратим в JSON, как требует Telegram
        if (isset($payload['reply_markup']) && is_array($payload['reply_markup'])) {
            $payload['reply_markup'] = json_encode($payload['reply_markup'], JSON_UNESCAPED_UNICODE);
        }

        $resp = Http::asForm()->post(
            "https://api.telegram.org/bot{$this->token}/sendMessage",
            $payload
        );

        return $resp->json();
    }

    public function setMyCommands(array $commands, ?string $languageCode = null): array
    {
        $payload = [
            'commands' => array_map(function ($c) {
                return [
                    'command'     => (string)($c['command'] ?? ''),
                    'description' => (string)($c['description'] ?? ''),
                ];
            }, $commands),
        ];

        if ($languageCode) {
            $payload['language_code'] = $languageCode;
        }

        $resp = Http::asJson()->post(
            "https://api.telegram.org/bot{$this->token}/setMyCommands",
            $payload
        );

        return $resp->json();
    }

    public function sendDocument(int|string $chatId, string $absolutePath, ?string $caption = null, array $opts = []): void
    {
        $token = config('services.telegram.bot_token');

        $multipart = [
            ['name' => 'chat_id', 'contents' => (string)$chatId],
            ['name' => 'document', 'contents' => fopen($absolutePath, 'r'), 'filename' => basename($absolutePath)],
        ];
        if ($caption) {
            $multipart[] = ['name' => 'caption', 'contents' => $caption];
        }
        foreach ($opts as $k => $v) {
            $multipart[] = ['name' => $k, 'contents' => is_string($v) ? $v : json_encode($v)];
        }

        Http::asMultipart()
            ->attach('document', fopen($absolutePath, 'r'), basename($absolutePath))
            ->post("https://api.telegram.org/bot{$token}/sendDocument", [
                'chat_id' => (string)$chatId,
                'caption' => $caption,
            ]);
    }

}
