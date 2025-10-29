<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Cache;

class ConversationState
{
    private string $key;

    public function __construct(string|int $chatId)
    {
        $this->key = "tg:state:{$chatId}";
    }

    public function get(): array
    {
        return Cache::get($this->key, []);
    }

    public function set(array $data, int $ttlSeconds = 600): void
    {
        Cache::put($this->key, $data, $ttlSeconds);
    }

    public function clear(): void
    {
        Cache::forget($this->key);
    }

    public function step(?string $value = null): ?string
    {
        if ($value === null) {
            return $this->get()['step'] ?? null;
        }
        $s = $this->get();
        $s['step'] = $value;
        $this->set($s);
        return $value;
    }

    public function put(string $key, mixed $value): void
    {
        $s = $this->get();
        $s[$key] = $value;
        $this->set($s);
    }
}
