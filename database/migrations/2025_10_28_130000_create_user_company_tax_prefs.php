<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_company_tax_prefs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tg_user_id')->index();
            $t->unsignedBigInteger('company_id')->index();
            $t->decimal('last_tax_rate', 6, 3)->nullable(); // %, напр. 2.000
            $t->string('last_period', 16)->nullable();      // "2025-10" или "2025-Q1"
            $t->timestamps();

            $t->unique(['tg_user_id','company_id'], 'uctp_user_company_unique');
        });
    }
    public function down(): void {
        Schema::dropIfExists('user_company_tax_prefs');
    }
};
