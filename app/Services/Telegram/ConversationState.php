<?php
namespace App\Services\Telegram;

use Illuminate\Support\Facades\Cache;

class ConversationState
{
    public function __construct(private int|string $chatId) {}

    private function key(): string
    {
        return "tg:state:{$this->chatId}";
    }

    public function get(): array
    {
        return Cache::get($this->key(), []);
    }

    public function set(array $state): void
    {
        Cache::put($this->key(), $state, now()->addHours(6));
    }

    public function put(string $k, mixed $v): void
    {
        $s = $this->get();
        $s[$k] = $v;
        $this->set($s);
    }

    public function step(?string $step = null): ?string
    {
        if ($step === null) {
            return $this->get()['step'] ?? null;
        }
        $s = $this->get();
        $s['step'] = $step;
        $this->set($s);
        return $step;
    }

    public function flow(?string $flow = null): ?string
    {
        if ($flow === null) {
            return $this->get()['flow'] ?? null;
        }
        $s = $this->get();
        $s['flow'] = $flow;
        $this->set($s);
        return $flow;
    }

    /** Если текущий flow другой — очищаем и ставим нужный. */
    public function ensureFlow(string $flow): void
    {
        $s = $this->get();
        if (($s['flow'] ?? null) !== $flow) {
            $this->set(['flow' => $flow]);
        }
    }

    /** При поступлении новой команды можно вызывать для очистки чужого flow */
    public function clearIfOtherFlow(string $flow): void
    {
        $s = $this->get();
        if (($s['flow'] ?? null) !== $flow) {
            $this->clear();
        }
    }

    public function clear(): void
    {
        Cache::forget($this->key());
    }
}
