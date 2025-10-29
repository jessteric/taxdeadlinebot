<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 0) защитимся от хвостов
        Schema::dropIfExists('deadline_rules_old');

        // 1) переименуем текущую таблицу
        Schema::rename('deadline_rules', 'deadline_rules_old');

        // 1.1) индексы в SQLite глобальные — снесём старые имена
        DB::statement('DROP INDEX IF EXISTS deadline_rules_regime_index;');
        DB::statement('DROP INDEX IF EXISTS deadline_rules_obligation_id_index;');

        // 2) Новая таблица
        Schema::create('deadline_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('obligation_id')->index();   // создаст deadline_rules_obligation_id_index
            $table->string('regime', 20)->index();                  // создаст deadline_rules_regime_index
            $table->json('rrule_json')->nullable();

            $table->unsignedTinyInteger('due_day')->nullable();
            $table->string('due_shift', 32)->default('none');       // CHECK эмулируем триггерами
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->string('holiday_calendar_code', 4)->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        // 2.1) Триггеры-валидации вместо CHECK
        DB::statement("
      CREATE TEMP TRIGGER dr_check_insert BEFORE INSERT ON deadline_rules
      FOR EACH ROW BEGIN
        SELECT CASE
          WHEN NEW.due_shift NOT IN ('none','next_business_day','prev_business_day') THEN
            RAISE(ABORT, 'CHECK constraint failed: due_shift')
        END;
      END;
    ");

        DB::statement("
      CREATE TEMP TRIGGER dr_check_update BEFORE UPDATE ON deadline_rules
      FOR EACH ROW BEGIN
        SELECT CASE
          WHEN NEW.due_shift NOT IN ('none','next_business_day','prev_business_day') THEN
            RAISE(ABORT, 'CHECK constraint failed: due_shift')
        END;
      END;
    ");

        // 3) Перенос данных (невалидные due_shift приведём к 'none')
        DB::insert("
      INSERT INTO deadline_rules (id, obligation_id, regime, rrule_json, due_day, due_shift, grace_days, holiday_calendar_code, is_active, created_at, updated_at)
      SELECT id, obligation_id, regime, rrule_json, due_day,
             CASE WHEN due_shift IN ('none','next_business_day','prev_business_day') THEN due_shift ELSE 'none' END,
             grace_days, holiday_calendar_code, is_active, created_at, updated_at
      FROM deadline_rules_old
    ");

        // 4) Дроп старой таблицы
        Schema::drop('deadline_rules_old');
    }

    public function down(): void
    {
        // Обратное преобразование — в простейшем виде: вернём только 'none'
        Schema::rename('deadline_rules', 'deadline_rules_new');

        Schema::create('deadline_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('obligation_id')->index();
            $table->string('regime', 20)->index();
            $table->json('rrule_json')->nullable();
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->string('due_shift', 32)->default('none'); // исходное состояние
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->string('holiday_calendar_code', 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::insert("
            INSERT INTO deadline_rules (id, obligation_id, regime, rrule_json, due_day, due_shift, grace_days, holiday_calendar_code, is_active, created_at, updated_at)
            SELECT id, obligation_id, regime, rrule_json, due_day, 'none', grace_days, holiday_calendar_code, is_active, created_at, updated_at
            FROM deadline_rules_new
        ");

        Schema::drop('deadline_rules_new');
    }
};
