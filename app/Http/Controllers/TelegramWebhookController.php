<?php

namespace App\Http\Controllers;

use App\Console\Commands\AddCompanyCommand;
use App\Console\Commands\CompaniesCommand;
use App\Console\Commands\CompanyActionsHandler;
use App\Services\Telegram\BotApi;
use App\UseCases\Telegram\Commands\RemindersCommand;
use App\UseCases\Telegram\Commands\TaxCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $expected = config('services.telegram.webhook_secret');
        if ($expected && $request->query('secret') !== $expected) {
            return response('Forbidden', 403);
        }

        $update = $request->json()->all();

        $chatId = $update['message']['chat']['id']
            ?? $update['callback_query']['message']['chat']['id']
            ?? null;

        $text = isset($update['message']['text']) ? trim((string)$update['message']['text']) : null;
        $callbackData = $update['callback_query']['data'] ?? null;

        try {
            if ($callbackData) {
                if (str_starts_with($callbackData, 'company.')) {
                    app(CompanyActionsHandler::class)->handle($chatId, $update);
                    return response('OK');
                }
                if (str_starts_with($callbackData, 'rem:')) {
                    app(RemindersCommand::class)->handle($chatId, $update);
                    return response('OK');
                }
            }

            $processed = app(AddCompanyCommand::class)->continueFlow($chatId, $update);
            if ($processed) {
                return response('OK');
            }

            if (app(TaxCommand::class)->continue($chatId, $update)) {
                return response('OK');
            }

            switch ($text) {
                case '/start':
                case '/help':
                    app(BotApi::class)->sendMessage($chatId, __('bot.welcome'));
                    break;

                case '/addcompany':
                    app(AddCompanyCommand::class)->startFlow($chatId);
                    break;

                case '/companies':
                    app(CompaniesCommand::class)->handle($chatId);
                    break;

                case '/tax':
                    app(TaxCommand::class)->start($chatId);
                    break;

                case '/reminders':
                    app(RemindersCommand::class)->handle($chatId, $update);
                    break;

                default:
                    if ($chatId) {
                        app(BotApi::class)->sendMessage(
                            $chatId,
                            "Unknown command. Try /addcompany, /companies or /next"
                        );
                    }
                    break;
            }

            return response('OK');
        } catch (\Throwable $e) {
            Log::error('TELEGRAM_WEBHOOK_ERROR', [
                'e'      => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
                'update' => $update,
            ]);

            if ($chatId) {
                app(BotApi::class)->sendMessage($chatId, "Internal error. Try again later.");
            }
            return response('OK');
        }
    }
}
