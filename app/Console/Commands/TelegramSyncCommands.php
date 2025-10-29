<?php

namespace App\Console\Commands;

use App\Services\Telegram\BotApi;
use Illuminate\Console\Command;

class TelegramSyncCommands extends Command
{
    protected $signature = 'telegram:sync-commands';
    protected $description = 'Sync /commands list to Telegram';

    public function handle(BotApi $api): int
    {
        // EN (default)
        $en = [
            ['command'=>'start',           'description'=>'Start/help'],
            ['command'=>'addcompany',      'description'=>'Add a company'],
            ['command'=>'companies',       'description'=>'My companies'],
            ['command'=>'next',            'description'=>'Upcoming deadlines'],
            ['command'=>'reminders',       'description'=>'Reminder settings'],
            ['command'=>'tax',             'description'=>'Quick tax calc'],
            ['command'=>'tax_history',     'description'=>'Last tax calculations'],
            ['command'=>'setcurrency',     'description'=>'Set company currency'],
            ['command'=>'setrate_default', 'description'=>'Set default tax rate'],
            ['command'=>'plan',            'description'=>'Current plan & limits'],
            ['command'=>'features',        'description'=>'Features & Pro'],
        ];
        $api->setMyCommands($en);

        // RU
        $ru = [
            ['command'=>'start',           'description'=>'Показать помощь'],
            ['command'=>'addcompany',      'description'=>'Добавить компанию/ИП/Самозанятого'],
            ['command'=>'companies',       'description'=>'Мои компании'],
            ['command'=>'next',            'description'=>'Ближайшие дэдлайны'],
            ['command'=>'reminders',       'description'=>'Настройки напоминаний'],
            ['command'=>'tax',             'description'=>'Быстрый калькулятор налогов'],
            ['command'=>'tax_history',     'description'=>'История налогов'],
            ['command'=>'setcurrency',     'description'=>'Установить валюту по умолчанию'],
            ['command'=>'setrate_default', 'description'=>'Установить ставку по умолчанию'],
            ['command'=>'plan',            'description'=>'Планы и лимиты'],
            ['command'=>'features',        'description'=>'Новые фичи'],
        ];
        $api->setMyCommands($ru, 'ru');

        $this->info('Telegram commands synced.');
        return self::SUCCESS;
    }
}
