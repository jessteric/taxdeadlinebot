<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\Telegram\BotApi;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bot:commands', function () {
    app(BotApi::class)->setMyCommands([
        ['command'=>'start',           'description'=>'Start/help'],
        ['command'=>'addcompany',      'description'=>'Add a company'],
        ['command'=>'companies',       'description'=>'My companies'],
        ['command'=>'next',            'description'=>'Upcoming deadlines'],
        ['command'=>'reminders',       'description'=>'Reminder settings'],
        ['command'=>'tax',             'description'=>'Quick tax calc'],
        ['command'=>'tax_history',     'description'=>'Last tax calculations'],
        ['command'=>'setcurrency',     'description'=>'Set company currency'],
        ['command'=>'setrate_default', 'description'=>'Set default tax rate'],
        ['command'=>'plan',            'description'=>'Plan & limits'],
        ['command'=>'features',        'description'=>'Features & Pro'],
    ]);
    $this->info('Bot commands updated.');
});
