<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tg_users', function (Blueprint $t) {
            $t->id();
            $t->string('telegram_id')->unique();
            $t->string('username')->nullable();
            $t->string('locale', 8)->default('en');
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('tg_users'); }
};
