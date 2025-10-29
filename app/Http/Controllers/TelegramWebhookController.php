<?php

namespace App\Http\Controllers;

use App\Services\Telegram\UpdateParser;
use App\Services\Telegram\UpdateRouter;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $expected = config('services.telegram.webhook_secret');
        if ($expected && $request->query('secret') !== $expected) {
            return response('Forbidden', 403);
        }

        $raw = $request->json()->all();

        /** @var UpdateParser $parser */
        $parser = app(UpdateParser::class);
        /** @var UpdateRouter $router */
        $router = app(UpdateRouter::class);

        $u = $parser->parse($raw);
        $router->dispatch($u);

        return response('OK');
    }
}
