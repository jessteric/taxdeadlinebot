<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Company extends Model
{
    public const TYPE_COMPANY       = 'company';
    public const TYPE_SOLE_PROP     = 'sole_prop';
    public const TYPE_SELF_EMPLOYED = 'self_employed';

    protected $fillable = [
        'tg_user_id',
        'name',
        'country_code',
        'tax_regime',
        'timezone',
        'subject_type',
        'person_name',
        'tax_id',
    ];

    protected $casts = [
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    /** Эмодзи по типу субъекта */
    public static function subjectEmoji(string $type): string
    {
        return match ($type) {
            self::TYPE_COMPANY       => '🏢',
            self::TYPE_SOLE_PROP     => '🧑‍💼',
            self::TYPE_SELF_EMPLOYED => '🧑‍💻',
            default                  => '📄',
        };
    }

    /** Короткий лейбл по типу субъекта (для вывода) */
    public static function subjectLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_COMPANY       => 'company',
            self::TYPE_SOLE_PROP     => 'sole_prop',
            self::TYPE_SELF_EMPLOYED => 'self_employed',
            default                  => $type,
        };
    }

    /** Готовая строка для списка (удобно дергать в чат) */
    public function displayLine(): string
    {
        $emoji = self::subjectEmoji($this->subject_type ?? '');
        $label = self::subjectLabel($this->subject_type ?? '');
        $who   = $this->person_name ? " / {$this->person_name}" : '';
        $tax   = $this->tax_id ? " / {$this->tax_id}" : '';

        return sprintf(
            "%d) %s %s%s — %s, %s, %s, %s%s",
            $this->id,
            $emoji,
            $this->name,
            $who,
            $label,
            $this->country_code,
            $this->tax_regime,
            $this->timezone,
            $tax
        );
    }

    public function tgUser(): BelongsTo
    {
        return $this->belongsTo(TgUser::class, 'tg_user_id');
    }

    /** BC-алиас на случай старого кода with('user') */
    public function user(): BelongsTo
    {
        return $this->tgUser();
    }
}
