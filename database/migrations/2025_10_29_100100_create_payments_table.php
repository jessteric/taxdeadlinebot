<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tg_user_id');
            $t->string('provider');           // stripe|yookassa|stars|...
            $t->string('external_id')->index();
            $t->string('status');             // pending|paid|failed|refunded
            $t->decimal('amount', 12, 2);
            $t->string('currency', 8)->default('EUR');
            $t->string('plan');               // pro|business
            $t->timestamp('started_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();

            $t->foreign('tg_user_id')->references('id')->on('tg_users')->cascadeOnDelete();
        });
    }
    public function down(): void {
        Schema::dropIfExists('payments');
    }
};
