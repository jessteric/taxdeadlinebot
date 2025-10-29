<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('companies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tg_user_id')->constrained('tg_users')->cascadeOnDelete();
            $t->string('name');
            $t->string('country_code', 2);
            $t->string('tax_regime', 16); // monthly|quarterly|annual (free text ok)
            $t->string('timezone', 64)->default('Europe/Brussels');
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('companies'); }
};
