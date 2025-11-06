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

    public function sendDocument(int|string $chatId, string $absolutePath, string $caption = ''): void
    {
        $token = config('services.telegram.bot_token');

        if (!$token) {
            Log::error('TG_DOC_FAIL', ['reason' => 'empty_token']);
            $this->sendMessage($chatId, "Не удалось отправить файл (нет токена).");
            return;
        }

        if (!is_file($absolutePath) || filesize($absolutePath) === 0) {
            Log::error('TG_DOC_FAIL', ['reason' => 'file_missing_or_empty', 'path' => $absolutePath]);
            $this->sendMessage($chatId, "Файл экспорта не найден или пустой.");
            return;
        }

        $url = "https://api.telegram.org/bot{$token}/sendDocument";

        try {
            $resp = \Illuminate\Support\Facades\Http::timeout(30)
                ->withOptions(['verify' => false])
                ->attach('document', fopen($absolutePath, 'r'), basename($absolutePath))
                ->post($url, [
                    'chat_id' => (string)$chatId,
                    'caption' => $caption,
                ]);

            Log::info('TG_SEND_DOCUMENT_RESP', [
                'status' => $resp->status(),
                'body'   => $resp->body(),
            ]);

            if (!$resp->ok()) {
                $this->sendMessage($chatId, "Не удалось отправить файл (HTTP {$resp->status()}).");
                return;
            }

            $json = $resp->json();
            if (!is_array($json) || empty($json['ok'])) {
                $desc = is_array($json) ? ($json['description'] ?? 'unknown') : 'no_json';
                $this->sendMessage($chatId, "Не удалось отправить файл: {$desc}");
                return;
            }
        } catch (\Throwable $e) {
            Log::error('TG_SEND_DOCUMENT_FAIL', ['e' => $e->getMessage()]);
            $this->sendMessage($chatId, "Не удалось отправить файл.");
        }
    }
}
