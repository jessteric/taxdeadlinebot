<?php

namespace App\Console\Commands;

use App\Models\HolidayCalendar;
use Illuminate\Console\Command;

class HolidaysImportCommand extends Command
{
    protected $signature = 'holidays:import {--country=} {--file=}';
    protected $description = 'Import holidays JSON file into holiday_calendars table';

    public function handle(): int
    {
        $country = strtoupper($this->option('country') ?? '');
        $file = base_path($this->option('file'));

        if (!$country || !file_exists($file)) {
            $this->error('Usage: php artisan holidays:import --country=BE --file=config/holidays/BE.json');
            return self::FAILURE;
        }

        $json = json_decode(file_get_contents($file), true);
        if (!is_array($json)) {
            $this->error('Invalid JSON format');
            return self::FAILURE;
        }

        $code = $json['code'] ?? $country;
        $dates = $json['data'] ?? [];

        HolidayCalendar::updateOrCreate(
            ['code' => $code],
            [
                'country_code' => $country,
                'data'         => $dates,
            ]
        );

        $this->info("✅ Imported holidays for {$country} (" . count($dates) . " dates)");
        return self::SUCCESS;
    }
}
