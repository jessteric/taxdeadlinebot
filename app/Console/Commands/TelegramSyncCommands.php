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
            ['command' => 'start',      'description' => 'Show help'],
            ['command' => 'addcompany', 'description' => 'Add a company / sole proprietor'],
            ['command' => 'companies',  'description' => 'Manage companies'],
            ['command' => 'next',       'description' => 'Upcoming deadlines'],
            ['command' => 'tax',        'description' => 'Tax calculator by period'],
            ['command' => 'reminders',  'description' => 'Reminders'],
        ];
        $api->setMyCommands($en); // default language

        // RU
        $ru = [
            ['command' => 'start',      'description' => 'Показать помощь'],
            ['command' => 'addcompany', 'description' => 'Добавить компанию/ИП'],
            ['command' => 'companies',  'description' => 'Управление компаниями'],
            ['command' => 'next',       'description' => 'Ближайшие дедлайны'],
            ['command' => 'tax',        'description' => 'Калькулятор налога по периоду'],
            ['command' => 'reminders',  'description' => 'Напоминания'],
        ];
        $api->setMyCommands($ru, 'ru');

        $this->info('Telegram commands synced.');
        return self::SUCCESS;
    }
}
