<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Services\Telegram\BotApi;

readonly class CompaniesCommand
{
    public function __construct(
        private CompanyRepositoryInterface $companies,
        private BotApi $api
    ) {}

    public function handle(int|string $chatId): void
    {
        $list = $this->companies->listForTelegram($chatId);

        if ($list->isEmpty()) {
            $this->api->sendMessage($chatId, "У вас пока нет субъектов. Используйте /addcompany.");
            return;
        }

        foreach ($list as $c) {
            /** @var Company $c */
            $emoji = Company::subjectEmoji($c->subject_type ?? '');
            $label = Company::subjectLabel($c->subject_type ?? '');
            $who   = $c->person_name ? " / {$c->person_name}" : '';
            $line  = "{$emoji} <b>{$c->name}</b>{$who}\n{$label}, {$c->country_code}, {$c->tax_regime}, {$c->timezone}";

            $this->api->sendMessage($chatId, $line, [
                'parse_mode' => 'HTML',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => '📊 Report', 'callback_data' => "company.report:{$c->id}"],
                            ['text' => '🗑️ Delete', 'callback_data' => "company.delete:{$c->id}"],
                        ],
                    ],
                ],
            ]);
        }
    }
}
