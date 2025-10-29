<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tg_users', function (Blueprint $t) {
            $t->string('plan')->default('free');
            $t->timestamp('plan_expires_at')->nullable();
        });
    }
    public function down(): void {
        Schema::table('tg_users', function (Blueprint $t) {
            $t->dropColumn(['plan', 'plan_expires_at']);
        });
    }
};
