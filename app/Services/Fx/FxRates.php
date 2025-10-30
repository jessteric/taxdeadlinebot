<?php

namespace App\Services\FX;

/**
 * MVP: таблица курсов в .env или дефолты.
 * Позже можно подменить на провайдера (ECB/Frankfurter/Exchangerate.host).
 */
final class FxRates
{
    /** Базовая валюта для таблицы (например, EUR) */
    private string $base;

    /** @var array<string,float> курс 1 BASE = X CUR */
    private array $rates;

    public function __construct(?string $base = null, ?array $rates = null)
    {
        $this->base  = $base  ?: (env('FX_BASE', 'EUR'));
        $json        = $rates ?: json_decode((string)env('FX_RATES_JSON', '{}'), true) ?: [];

        // Дефолтные примерные курсы (обнови под себя)
        $defaults = [
            'EUR' => 1.0,
            'USD' => 1.08,
            'GEL' => 2.95,
            'RUB' => 98.0,
            'BYN' => 3.5,
            'AMD' => 420.0,
            'PLN' => 4.35,
            'KZT' => 500.0,
        ];

        $this->rates = $json + $defaults;
        $this->rates[$this->base] = 1.0; // база = 1
    }

    public function getBase(): string
    {
        return $this->base;
    }

    public function canConvert(string $from, string $to): bool
    {
        $f = strtoupper($from);
        $t = strtoupper($to);
        return isset($this->rates[$f]) && isset($this->rates[$t]);
    }

    /**
     * Конвертирует amount из $from в $to.
     * Таблица задаёт 1 BASE = X CUR → переводим через базу.
     */
    public function convert(float $amount, string $from, string $to): float
    {
        $from = strtoupper($from);
        $to   = strtoupper($to);

        if ($from === $to) {
            return $amount;
        }
        if (!$this->canConvert($from, $to)) {
            throw new \RuntimeException("FX: unsupported pair $from->$to");
        }

        // amount[from] → base → to
        $toBase   = $amount / $this->rates[$from];
        $toTarget = $toBase * $this->rates[$to];

        return $toTarget;
    }
}
