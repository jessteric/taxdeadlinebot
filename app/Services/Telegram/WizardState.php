<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Cache;

final class WizardState
{
    private const TTL = 600;

    private function key(int|string $chatId): string
    {
        return "tg:wizard:{$chatId}";
    }

    public function start(int|string $chatId, string $flow, array $data = []): void
    {
        Cache::put($this->key($chatId), ['flow'=>$flow, 'step'=>1, 'data'=>$data], self::TTL);
    }

    public function get(int|string $chatId): ?array
    {
        /** @var array|null $chatId */
        return Cache::get($this->key($chatId));
    }

    public function set(int|string $chatId, array $state): void
    {
        Cache::put($this->key($chatId), $state, self::TTL);
    }

    public function clear(int|string $chatId): void
    {
        Cache::forget($this->key($chatId));
    }
}
