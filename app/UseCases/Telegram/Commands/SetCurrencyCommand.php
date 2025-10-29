<?php

namespace App\UseCases\Telegram\Commands;

use App\Models\TgUser;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Services\Telegram\BotApi;
use App\Services\Telegram\ConversationState;

readonly class SetCurrencyCommand
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
            $kb = $list->map(fn($c)=>[[ 'text'=>$c->name, 'callback_data'=>"sc:pick:{$c->id}" ]])->values()->all();
            $this->api->sendMessage($chatId, "Выберите компанию:", ['reply_markup'=>['inline_keyboard'=>$kb]]);
            return;
        }

        $this->askCurrency($chatId, $list->first()->id);
    }

    public function continue(int|string $chatId, array $update): bool
    {
        $cb = $update['callback_query']['data'] ?? null;
        $text = trim((string)($update['message']['text'] ?? ''));

        if (is_string($cb) && str_starts_with($cb, 'sc:pick:')) {
            $cid = (int)substr($cb, strlen('sc:pick:'));
            $this->askCurrency($chatId, $cid);
            return true;
        }

        $state = new ConversationState($chatId);
        if ($state->step() === 'sc_currency') {
            $cid = (int)($state->get()['company_id'] ?? 0);
            $code = strtoupper(preg_replace('~[^A-Z]~', '', $text));
            if (!preg_match('~^[A-Z]{3}$~', $code)) {
                $this->api->sendMessage($chatId, "Введите валюту ISO-4217, напр.: EUR, USD, BYN, RUB, GEL, AMD, AZN, PLN, UAH.");
                return true;
            }
            $this->companies->updateCurrency($cid, $code);
            $state->clear();
            $this->api->sendMessage($chatId, "Валюта компании обновлена: {$code}");
            return true;
        }

        return false;
    }

    private function askCurrency(int|string $chatId, int $companyId): void
    {
        (new ConversationState($chatId))->set([
            'step' => 'sc_currency',
            'payload' => ['company_id' => $companyId],
        ]);

        $this->api->sendMessage($chatId,
            "Введите валюту ISO-4217 (3 буквы), напр.: EUR, USD, BYN, RUB, GEL, AMD, AZN, PLN, UAH."
        );
    }
}
