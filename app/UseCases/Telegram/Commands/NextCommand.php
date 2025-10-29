<?php

namespace App\UseCases\Telegram\Commands;

use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Services\Telegram\BotApi;
use App\Services\Telegram\UpdateHelper;
use Carbon\CarbonImmutable;

readonly class NextCommand
{
    public function __construct(
        private EventRepositoryInterface    $events,
        private CompanyRepositoryInterface  $companies,
        private BotApi                      $api
    ) {}

    public function handle(int|string $chatId, array $update = []): void
    {
        // обрабатываем клики пагинации/фильтра
        if (!empty($update['callback_query']['data']) &&
            str_starts_with($update['callback_query']['data'], 'next:')) {
            $this->handleCallback($chatId, $update['callback_query']['data']);
            return;
        }

        $this->sendList($chatId);
    }

    private function sendList(int|string $chatId, ?int $companyId = null, int $page = 1): void
    {
        $perPage = 5;
        $offset = ($page - 1) * $perPage;
        $list = $this->events->nextForUserChat($chatId, $perPage, $offset, $companyId); // расширь репозиторий: limit, offset, companyId
        $total = $this->events->countUpcomingForUserChat($chatId, $companyId);

        if ($list->isEmpty()) {
            $this->api->sendMessage($chatId, __('bot.next.empty'));
            return;
        }

        $today = CarbonImmutable::today();
        $lines = [];
        foreach ($list as $e) {
            $dueLocal = $e->due_at?->timezone($e->company->timezone)->toImmutable() ?? $today;
            $badge = '⚪';
            if ($dueLocal->lt($today)) $badge = '🔴';
            elseif ($dueLocal->isSameDay($today)) $badge = '🟠';
            elseif ($dueLocal->diffInDays($today, false) > -7) $badge = '🟢';

            $lines[] = $badge . ' ' . __('bot.next.line', [
                    'title'   => $e->obligation->title,
                    'due'     => $dueLocal->format('Y-m-d'),
                    'from'    => $e->period_from->format('Y-m-d'),
                    'to'      => $e->period_to->format('Y-m-d'),
                    'company' => $e->company->name,
                ]);
        }

        $text = __('bot.next.header') . "\n" . implode("\n", $lines);

        // кнопки фильтра компаний
        $companies = $this->companies->listForTelegram($chatId);
        $filterRow = $companies->count() > 1
            ? $companies->take(5)->map(fn($c) => [
                'text' => ($companyId === $c->id ? "✅ " : "") . mb_strimwidth($c->name, 0, 14, '…'),
                'callback_data' => "next:filter:{$c->id}:1",
            ])->all()
            : [];

        // пагинация
        $maxPage = max(1, (int)ceil($total / $perPage));
        $pager = [];
        if ($page > 1)   $pager[] = ['text' => '◀️', 'callback_data' => "next:page:" . ($companyId ?? 0) . ":" . ($page - 1)];
        if ($page < $maxPage) $pager[] = ['text' => '▶️', 'callback_data' => "next:page:" . ($companyId ?? 0) . ":" . ($page + 1)];

        $kb = [];
        if (!empty($filterRow)) $kb[] = $filterRow;
        if (!empty($pager))     $kb[] = $pager;

        $this->api->sendMessage($chatId, $text, ['parse_mode' => 'HTML', 'reply_markup' => ['inline_keyboard' => $kb]]);
    }

    private function handleCallback(int|string $chatId, string $data): void
    {
        // next:filter:{companyId}:{page} | next:page:{companyId}:{page}
        $parts = explode(':', $data);
        if (count($parts) < 4) { $this->sendList($chatId); return; }

        [$ns, $action, $companyRaw, $pageRaw] = $parts;
        $companyId = (int)$companyRaw ?: null;
        $page = max(1, (int)$pageRaw);

        $this->sendList($chatId, $companyId, $page);
    }
}
