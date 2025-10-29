<?php

namespace App\Services\Telegram;

use Closure;
use Illuminate\Support\Facades\Log;

class UpdateRouter
{
    /** @var array<string,Closure> */
    private array $callbackPrefixes = [];
    /** @var array<string,Closure> */
    private array $commands = [];
    /** @var array<int,Closure> */
    private array $continuations = [];

    public function onCallbackPrefix(string $prefix, Closure $handler): self
    {
        $this->callbackPrefixes[$prefix] = $handler;
        return $this;
    }

    public function onCommand(string $command, Closure $handler): self
    {
        $this->commands['/'.$command] = $handler;
        return $this;
    }

    public function addContinuation(Closure $handler): self
    {
        $this->continuations[] = $handler;
        return $this;
    }

    public function dispatch(ParsedUpdate $u): void
    {
        Log::info('TG_DISPATCH_START', [
            'chatId' => $u->chatId,
            'text'   => $u->text,
            'cb'     => $u->callbackData,
        ]);

        // 1) CALLBACKS
        if ($u->callbackData) {
            foreach ($this->callbackPrefixes as $prefix => $handler) {
                if (str_starts_with($u->callbackData, $prefix)) {
                    Log::info('TG_CB_MATCH', ['prefix' => $prefix]);
                    $handler($u);
                    return;
                }
            }
            Log::warning('TG_CB_NO_MATCH');
            return;
        }

        // 2) COMMANDS (сброс state перед новой командой)
        if ($u->text && str_starts_with($u->text, '/')) {
            $first = strtok($u->text, ' ') ?: $u->text;
            $base  = strtolower(explode('@', $first)[0]);

            if (isset($this->commands[$base])) {
                Log::info('TG_CMD_MATCH', ['cmd' => $base]);
                (new ConversationState($u->chatId))->clear();
                ($this->commands[$base])($u);
                return;
            }

            // неизвестная команда — ещё дадим шанс continuations (на случай /start с текстом, но у нас не нужно)
            Log::warning('TG_CMD_UNKNOWN', ['cmd' => $base]);
        }

        // 3) CONTINUATIONS (flows)
        foreach ($this->continuations as $i => $cont) {
            $handled = false;
            try {
                $handled = (bool)$cont($u);
            } catch (\Throwable $e) {
                Log::error('TG_CONT_ERROR', ['i' => $i, 'e' => $e->getMessage()]);
            }
            if ($handled) {
                Log::info('TG_CONT_HANDLED', ['i' => $i]);
                return;
            }
        }

        // 4) Фоллбек — отвечаем Unknown ТОЛЬКО если это была команда
        if ($u->text && str_starts_with($u->text, '/')) {
            app(BotApi::class)->sendMessage(
                $u->chatId,
                "Unknown command. Try /addcompany, /companies or /next"
            );
        } else {
            Log::info('TG_NO_HANDLER');
        }
    }
}
