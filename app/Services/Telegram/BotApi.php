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

    public function sendDocument(int|string $chatId, string $filename, string $contents, array $params = []): array
    {
        $multipart = [
            ['name' => 'chat_id', 'contents' => (string)$chatId],
            ['name' => 'document', 'contents' => $contents, 'filename' => $filename],
        ];
        foreach ($params as $k => $v) {
            $multipart[] = [
                'name' => $k,
                'contents' => is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE)
            ];
        }

        $resp = Http::asMultipart()
            ->post("https://api.telegram.org/bot{$this->token}/sendDocument", $multipart);

        return $resp->json();
    }

}
