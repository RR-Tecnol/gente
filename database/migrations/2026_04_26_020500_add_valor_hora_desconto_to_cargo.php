<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('CARGO') && !Schema::hasColumn('CARGO', 'CARGO_VALOR_HORA_DESCONTO')) {
            Schema::table('CARGO', function (Blueprint $table) {
                $table->decimal('CARGO_VALOR_HORA_DESCONTO', 12, 2)->nullable()->after('CARGO_REMUNERACAO');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('CARGO') && Schema::hasColumn('CARGO', 'CARGO_VALOR_HORA_DESCONTO')) {
            Schema::table('CARGO', function (Blueprint $table) {
                $table->dropColumn('CARGO_VALOR_HORA_DESCONTO');
            });
        }
    }
};

