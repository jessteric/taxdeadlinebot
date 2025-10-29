<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('holiday_calendars', function (Blueprint $t) {
            $t->string('code', 16)->primary(); // e.g., 'BE'
            $t->string('country_code', 2)->index();
            $t->jsonb('data');
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('holiday_calendars'); }
};
