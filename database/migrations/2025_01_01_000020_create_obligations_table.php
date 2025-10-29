<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('obligations', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('country_code', 2)->index();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('obligations'); }
};
