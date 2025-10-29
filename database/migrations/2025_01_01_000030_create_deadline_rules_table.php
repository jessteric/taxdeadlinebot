<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('deadline_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('obligation_id')->constrained()->cascadeOnDelete();
            $t->string('regime', 16);
            $t->jsonb('rrule_json')->nullable();
            $t->unsignedInteger('due_day')->nullable();
            $t->enum('due_shift', ['none','next_business','prev_business'])->default('next_business');
            $t->unsignedInteger('grace_days')->default(0);
            $t->string('holiday_calendar_code', 16)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->index(['regime']);
        });
    }
    public function down(): void { Schema::dropIfExists('deadline_rules'); }
};
