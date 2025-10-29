<?php

namespace App\Console\Commands;

use App\Models\DeadlineRule;
use App\Models\Obligation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;

class DeadlinesImportCommand extends Command
{
    protected $signature = 'deadlines:import {--country=} {--file=}';
    protected $description = 'Import obligation and deadline rules from YAML config file';

    public function handle(): int
    {
        $country = strtoupper($this->option('country') ?? '');
        $file = base_path($this->option('file'));

        if (!$country || !file_exists($file)) {
            $this->error('Usage: php artisan deadlines:import --country=BE --file=config/deadlines/BE.yml');
            return self::FAILURE;
        }

        $data = Yaml::parseFile($file);
        if (empty($data['obligations'])) {
            $this->warn('No obligations found in YAML');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($data, $country) {
            foreach ($data['obligations'] as $ob) {
                /** @var Obligation $obligation */
                $obligation = Obligation::updateOrCreate(
                    ['code' => $ob['code']],
                    [
                        'title'        => $ob['title'],
                        'description'  => $ob['description'] ?? '',
                        'country_code' => $ob['country_code'] ?? $country,
                        'is_active'    => true,
                    ]
                );

                foreach ($ob['rules'] ?? [] as $rule) {
                    DeadlineRule::updateOrCreate(
                        [
                            'obligation_id' => $obligation->id,
                            'regime'        => $rule['regime'],
                        ],
                        [
                            'rrule_json'          => $rule['rrule'] ?? [],
                            'due_day'             => $rule['due_day'] ?? null,
                            'due_shift'           => $rule['due_shift'] ?? 'none',
                            'grace_days'          => $rule['grace_days'] ?? 0,
                            'holiday_calendar_code' => $rule['holiday_calendar_code'] ?? $country,
                            'is_active'           => $rule['is_active'] ?? true,
                        ]
                    );
                }
            }
        });

        $this->info("✅ Imported deadlines for {$country}");
        return self::SUCCESS;
    }
}
