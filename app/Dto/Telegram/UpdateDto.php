<?php

namespace App\Dto\Telegram;

final class UpdateDto
{
    public function __construct(
        public readonly array       $raw,
        public readonly int|string  $chatId,
        public readonly ?string     $text,
        public readonly ?string     $callbackData,
        public readonly ?int        $messageId,
        public readonly ?string     $username,
        public readonly ?string     $locale,
    ) {}

    public function isCommand(): bool
    {
        return is_string($this->text) && str_starts_with(trim($this->text), '/');
    }

    public function hasCallback(): bool
    {
        return $this->callbackData !== null && $this->callbackData !== '';
    }
}
