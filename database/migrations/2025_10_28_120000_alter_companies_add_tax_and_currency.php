<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('companies', function (Blueprint $t) {
            $t->string('pay_currency', 3)->default('EUR')->after('timezone');
            $t->decimal('default_tax_rate', 6, 3)->nullable()->after('pay_currency');
        });
    }
    public function down(): void {
        Schema::table('companies', function (Blueprint $t) {
            $t->dropColumn(['pay_currency', 'default_tax_rate']);
        });
    }
};
