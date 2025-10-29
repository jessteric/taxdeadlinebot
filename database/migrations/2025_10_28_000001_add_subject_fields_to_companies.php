<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('subject_type', 20)->default('company')->index(); // company|sole_prop|self_employed
            $table->string('person_name', 160)->nullable(); // для ИП/самозанятых
            $table->string('tax_id', 64)->nullable();       // ИНН/УНП/рег.номер (опц.)
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['subject_type', 'person_name', 'tax_id']);
        });
    }
};
