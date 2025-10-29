<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

Route::post('/telegram/webhook', TelegramWebhookController::class);

Route::post('/ping', function (Request $r) {
    Log::info('PING hit', ['body' => $r->getContent()]);
    return response('PONG', 200);
});
