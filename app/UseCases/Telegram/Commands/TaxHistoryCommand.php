<?php

namespace App\UseCases\Telegram\Commands;

use App\Models\Company;
use App\Models\TaxCalculation;
use App\Models\TgUser;
use App\Services\Billing\Features;
use App\Services\Telegram\BotApi;
use App\Services\Telegram\ConversationState;

class TaxHistoryCommand
{
    private const PAGE = 5;

    public function __construct(
        private BotApi $api,
    ) {}

    /**
     * /tax_history — старт: показывает последние N по всем компаниям
     */
    public function handle(int|string $chatId, array $update = []): void
    {
        // поддержка коллбэка "hist:more:<offset>"
        if (isset($update['callback_query']['data'])
            && str_starts_with($update['callback_query']['data'], 'hist:more:')
        ) {
            $parts = explode(':', $update['callback_query']['data']);
            $offset = (int)($parts[2] ?? 0);
            $this->showList($chatId, $offset);
            return;
        }

        // обычный вызов
        (new ConversationState($chatId))->clear();
        $this->showList($chatId, 0);
    }

    /**
     * Продолжение не требуется — но оставим подпись для router'а
     */
    public function continue(int|string $chatId, array $update): bool
    {
        // здесь ничего не перехватываем
        return false;
    }

    private function showList(int|string $chatId, int $offset): void
    {
        $u = TgUser::query()->byTelegramId($chatId)->first();
        if (!$u) {
            $this->api->sendMessage($chatId, "Сначала /start.");
            return;
        }

        // Базовый запрос по всем расчётам пользователя
        $q = TaxCalculation::query()
            ->with('company:id,name')
            ->where('tg_user_id', $u->id)
            ->orderByDesc('created_at');

        $total = (clone $q)->count();

        $planLimit = Features::historyLimit($u);
        $effectiveTotal = min($total, $planLimit);

        if ($effectiveTotal === 0) {
            $this->api->sendMessage($chatId, "Пока нет сохранённых расчётов.");
            return;
        }

        if ($offset >= $effectiveTotal) {
            $offset = max(0, $effectiveTotal - self::PAGE);
        }

        $remainingWithinLimit = max(0, $effectiveTotal - $offset);

        $pageSize = min(self::PAGE, $remainingWithinLimit);

        $rows = $q->offset($offset)->limit($pageSize)->get();

        if ($rows->isEmpty()) {
            $this->api->sendMessage($chatId, $offset === 0
                ? "Пока нет сохранённых расчётов."
                : "Больше записей нет.");
            return;
        }

        $lines = [];
        $lines[] = "Последние расчёты:";
        foreach ($rows as $r) {
            $period = $r->period_label;
            if (!$period) {
                $from = $r->period_from?->format('Y-m-d');
                $to   = $r->period_to?->format('Y-m-d');
                if ($from && $to) {
                    $period = "{$from}–{$to}";
                } elseif ($from) {
                    $period = $from;
                } else {
                    $period = '—';
                }
            }

            $company = $r->company?->name ?? '—';

            $income = $this->fmt($r->income);
            $rate   = $this->fmt($r->rate);
            $pay    = $this->fmt($r->pay_amount);
            $cur    = $r->pay_currency ?: '';

            $lines[] = sprintf(
                "• %s — %s @ %s%% → налог %s %s (%s)",
                $period,
                $income,
                $rate,
                $pay,
                $cur,
                $company
            );
        }

        $kb = [];
        if ($offset + $pageSize < $effectiveTotal) {
            $kb = [
                [
                    ['text' => 'Показать ещё', 'callback_data' => 'hist:more:' . ($offset + $pageSize)],
                ],
            ];
        }

        $this->api->sendMessage($chatId, implode("\n", $lines), [
            'reply_markup' => $kb ? ['inline_keyboard' => $kb] : null,
        ]);

        if ($effectiveTotal < $total) {
            $this->api->sendMessage(
                $chatId,
                "Показаны только первые {$planLimit} записей на Free. Открой /plan, чтобы разблокировать безлимит и экспорт."
            );
        }
    }

    private function fmt(null|float|int|string $v): string
    {
        if ($v === null || $v === '') return '—';
        return number_format((float)$v, 2, '.', ' ');
    }
}
