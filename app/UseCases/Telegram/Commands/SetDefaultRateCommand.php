<?php

namespace App\UseCases\Telegram\Commands;

use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Services\Telegram\BotApi;
use App\Services\Telegram\ConversationState;

readonly class SetDefaultRateCommand
{
    public function __construct(
        private CompanyRepositoryInterface $companies,
        private BotApi $api
    ) {}

    public function handle(int|string $chatId, array $update = []): void
    {
        $list = $this->companies->listForTelegram($chatId);
        if ($list->isEmpty()) { $this->api->sendMessage($chatId, "Нет компаний. /addcompany"); return; }

        if ($list->count() > 1) {
            $kb = $list->map(fn($c)=>[[ 'text'=>$c->name, 'callback_data'=>"srd:pick:{$c->id}" ]])->values()->all();
            $this->api->sendMessage($chatId, "Выберите компанию:", ['reply_markup'=>['inline_keyboard'=>$kb]]);
            return;
        }

        $this->askRate($chatId, $list->first()->id);
    }

    public function continue(int|string $chatId, array $update): bool
    {
        $cb = $update['callback_query']['data'] ?? null;
        $text = trim((string)($update['message']['text'] ?? ''));

        if (is_string($cb) && str_starts_with($cb, 'srd:pick:')) {
            $cid = (int)substr($cb, strlen('srd:pick:'));
            $this->askRate($chatId, $cid);
            return true;
        }

        $state = new ConversationState($chatId);
        if ($state->step() === 'srd_rate') {
            $cid = (int)($state->get()['company_id'] ?? 0);
            if (!preg_match('~^\d+(\.\d+)?$~', $text)) {
                $this->api->sendMessage($chatId, "Введите число (процент), напр.: 2 или 20.5");
                return true;
            }
            $rate = (float)$text;
            $this->companies->updateDefaultRate($cid, $rate);
            $state->clear();
            $this->api->sendMessage($chatId, "Дефолтная ставка обновлена: {$rate}%");
            return true;
        }

        return false;
    }

    private function askRate(int|string $chatId, int $companyId): void
    {
        (new ConversationState($chatId))->set([
            'step' => 'srd_rate',
            'payload' => ['company_id' => $companyId],
        ]);
        $this->api->sendMessage($chatId, "Введите дефолтную ставку налога в процентах (например, 2 или 20.5).");
    }
}
