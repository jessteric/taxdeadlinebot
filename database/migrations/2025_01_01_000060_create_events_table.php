<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('obligation_id')->constrained()->cascadeOnDelete();
            $t->date('period_from');
            $t->date('period_to');
            $t->timestampTz('due_at');
            $t->enum('status', ['upcoming','sent','done','skipped'])->default('upcoming');
            $t->jsonb('meta')->nullable();
            $t->timestamps();

            $t->index(['company_id','due_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('events'); }
};
