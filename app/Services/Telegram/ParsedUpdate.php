<?php

namespace App\Services\Telegram;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class ParsedUpdate
{
    public function __construct(
        public int|string|null $chatId,
        public ?string $text,
        public ?string $callbackData,
        public array $raw,
    ) {}
}
