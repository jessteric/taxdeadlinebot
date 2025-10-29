<?php

namespace App\Providers;

use App\Console\Commands\AddCompanyCommand;
use App\Console\Commands\CompaniesCommand;
use App\Console\Commands\CompanyActionsHandler;
use App\Services\Telegram\BotApi;
use App\Services\Telegram\UpdateParser;
use App\Services\Telegram\UpdateRouter;
use App\UseCases\Telegram\Commands\FeaturesCommand;
use App\UseCases\Telegram\Commands\PlanCommand;
use App\UseCases\Telegram\Commands\RemindersCommand;
use App\UseCases\Telegram\Commands\SetCurrencyCommand;
use App\UseCases\Telegram\Commands\SetDefaultRateCommand;
use App\UseCases\Telegram\Commands\TaxCommand;
use App\UseCases\Telegram\Commands\TaxHistoryCommand;
use Illuminate\Support\ServiceProvider;

class TelegramServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UpdateParser::class);
        $this->app->singleton(UpdateRouter::class, function ($app) {
            $router = new UpdateRouter();

            // ====== CALLBACKS ======
            $router
                ->onCallbackPrefix('company.', function ($u) use ($app) {
                    $app->make(CompanyActionsHandler::class)->handle($u->chatId, $u->raw);
                })
                ->onCallbackPrefix('rem:', function ($u) use ($app) {
                    $app->make(RemindersCommand::class)->handle($u->chatId, $u->raw);
                })
                ->onCallbackPrefix('ac:', function ($u) use ($app) {
                    $app->make(AddCompanyCommand::class)
                        ->handleCallback($u->chatId, $u->raw);
                })
                ->onCallbackPrefix('cur:', function ($u) use ($app) {
                    $app->make(SetCurrencyCommand::class)->handle($u->chatId, $u->raw);
                })
                ->onCallbackPrefix('hist:', function ($u) use ($app) {
                    $app->make(TaxHistoryCommand::class)->handle($u->chatId, $u->raw);
                })
                ->onCallbackPrefix('tax.pick_company:', function ($u) use ($app) {
                    $app->make(TaxCommand::class)
                        ->continue($u->chatId, $u->raw);
                })
                ->onCallbackPrefix('tax.rate:', function ($u) use ($app) {
                    $app->make(TaxCommand::class)
                        ->continue($u->chatId, $u->raw);
                })
                ->onCallbackPrefix('th.pick_company:', function ($u) use ($app) {
                    $app->make(TaxHistoryCommand::class)
                        ->continue($u->chatId, $u->raw);
                })
                ->onCallbackPrefix('sc.pick_company:', function ($u) use ($app) {
                    $app->make(SetCurrencyCommand::class)
                        ->continue($u->chatId, $u->raw);
                })
                ->onCallbackPrefix('sdr.pick_company:', function ($u) use ($app) {
                    $app->make(SetDefaultRateCommand::class)
                        ->continue($u->chatId, $u->raw);
                });

            // ====== COMMANDS ======
            $router
                ->onCommand('start', function ($u) use ($app) {
                    $app->make(BotApi::class)->sendMessage($u->chatId, __('bot.welcome'));
                })
                ->onCommand('help', function ($u) use ($app) {
                    $app->make(BotApi::class)->sendMessage($u->chatId, __('bot.welcome'));
                })
                ->onCommand('addcompany', function ($u) use ($app) {
                    $app->make(AddCompanyCommand::class)->startFlow($u->chatId);
                })
                ->onCommand('companies', function ($u) use ($app) {
                    $app->make(CompaniesCommand::class)->handle($u->chatId);
                })
                ->onCommand('tax', function ($u) use ($app) {
                    $app->make(TaxCommand::class)->start($u->chatId);
                })
                ->onCommand('tax_history', function ($u) use ($app) {
                    $app->make(TaxHistoryCommand::class)->handle($u->chatId, $u->raw);
                })
                ->onCommand('setcurrency', function ($u) use ($app) {
                    $app->make(SetCurrencyCommand::class)->handle($u->chatId, $u->raw);
                })
                ->onCommand('setrate_default', function ($u) use ($app) {
                    $app->make(SetDefaultRateCommand::class)->handle($u->chatId, $u->raw);
                })
                ->onCommand('plan', function ($u) use ($app) {
                    $app->make(PlanCommand::class)->handle($u->chatId);
                })
                ->onCommand('features', function ($u) use ($app) {
                    $app->make(FeaturesCommand::class)->handle($u->chatId);
                })
                ->onCommand('reminders', function ($u) use ($app) {
                    $app->make(RemindersCommand::class)->handle($u->chatId, $u->raw);
                });

            // ====== CONTINUATIONS (stateful flows) ======
            // Важно: порядок — от более «узких» к более «широким»
            $router
                ->addContinuation(function ($u) use ($app) {
                    return $app->make(AddCompanyCommand::class)->continueFlow($u->chatId, $u->raw) === true;
                })
                ->addContinuation(function ($u) use ($app) {
                    return $app->make(TaxCommand::class)->continue($u->chatId, $u->raw) === true;
                })
                ->addContinuation(function ($u) use ($app) {
                    return $app->make(TaxHistoryCommand::class)->continue($u->chatId, $u->raw) === true;
                })
                ->addContinuation(function ($u) use ($app) {
                    return $app->make(SetCurrencyCommand::class)->continue($u->chatId, $u->raw) === true;
                })
                ->addContinuation(function ($u) use ($app) {
                    return $app->make(SetDefaultRateCommand::class)->continue($u->chatId, $u->raw) === true;
                })
                ->addContinuation(function ($u) use ($app) {
                    return $app->make(RemindersCommand::class)->continue($u->chatId, $u->raw) === true;
                });

            return $router;
        });
    }

    public function boot(): void
    {
        // no-op
    }
}
