<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tax_calculations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tg_user_id')->index();
            $t->unsignedBigInteger('company_id')->index();

            // период
            $t->date('period_from')->nullable();
            $t->date('period_to')->nullable();
            $t->string('period_label', 16)->index(); // "2025-10" или "2025-Q1"

            // входные и результат
            $t->decimal('income', 18, 2);
            $t->decimal('rate', 6, 3); // %
            $t->decimal('pay_amount', 18, 2);
            $t->string('pay_currency', 3);

            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('tax_calculations');
    }
};
