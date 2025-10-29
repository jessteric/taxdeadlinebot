<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('reminder_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tg_user_id')->index();
            $t->unsignedBigInteger('company_id')->index();
            $t->boolean('enabled')->default(true);
            $t->string('time_local', 5)->default('09:00'); // HH:MM
            $t->json('days_before')->default(json_encode([7,3,1]));
            $t->timestamps();
            $t->unique(['tg_user_id','company_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('reminder_settings');
    }
};
