<?php

namespace App\Services\Tax;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;

class PeriodParser
{
    /**
     * Принимает строки вида:
     *  - "2025-10" (месяц)
     *  - "2025/10"
     *  - "2025-Q1", "2025 Q3", "2025q4"
     *
     * @return array{from:DateTimeImmutable,to:DateTimeImmutable,label:string,type:"month"|"quarter"}
     * @throws InvalidArgumentException|Exception
     */
    public function parse(string $input): array
    {
        $s = strtoupper(trim($input));

        // Месяц: 2025-10 или 2025/10
        if (preg_match('~^(\d{4})[-/](0[1-9]|1[0-2])$~', $s, $m)) {
            $y = (int)$m[1];
            $mth = (int)$m[2];
            $from = new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $mth));
            $to = $from->modify('last day of this month');
            return [
                'from' => $from,
                'to'   => $to,
                'label'=> sprintf('%04d-%02d', $y, $mth),
                'type' => 'month',
            ];
        }

        // Квартал: 2025-Q1 / 2025 Q2 / 2025Q3
        if (preg_match('~^(\d{4})\s*-?\s*Q([1-4])$~', $s, $m)) {
            $y = (int)$m[1];
            $q = (int)$m[2];
            $startMonth = 1 + ($q - 1) * 3;
            $from = new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $startMonth));
            $to = $from->modify('+2 months')->modify('last day of this month');
            return [
                'from' => $from,
                'to'   => $to,
                'label'=> sprintf('%04d-Q%d', $y, $q),
                'type' => 'quarter',
            ];
        }

        throw new InvalidArgumentException('Некорректный формат периода. Пример: 2025-10 или 2025-Q4');
    }
}
