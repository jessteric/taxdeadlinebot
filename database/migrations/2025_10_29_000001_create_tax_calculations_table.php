<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Таблица истории расчётов — создаём, только если её ещё нет
        if (!Schema::hasTable('tax_calculations')) {
            Schema::create('tax_calculations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tg_user_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('period', 32)->index(); // e.g. 2025-10 / 2025-Q3
                $table->decimal('income_amount', 16, 2);
                $table->string('currency', 8)->default('EUR');
                $table->decimal('rate_percent', 8, 3);
                $table->decimal('tax_amount', 16, 2);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->foreign('tg_user_id')->references('id')->on('tg_users')->cascadeOnDelete();
                $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            });
        }
        // Если таблица уже есть — тихо пропускаем (ничего не ломаем).

        // 2) Доп. колонки в companies — добавляем только если их нет
        if (Schema::hasTable('companies')) {
            if (!Schema::hasColumn('companies', 'currency')) {
                Schema::table('companies', function (Blueprint $table) {
                    $table->string('currency', 8)->default('EUR')->after('timezone');
                });
            }
            if (!Schema::hasColumn('companies', 'default_tax_rate')) {
                Schema::table('companies', function (Blueprint $table) {
                    $table->decimal('default_tax_rate', 8, 3)->nullable()->after('currency');
                });
            }
        }
    }

    public function down(): void
    {
        // В SQLite drop/rename ограничены — аккуратно:
        if (Schema::hasTable('tax_calculations')) {
            Schema::drop('tax_calculations');
        }
    }
};
