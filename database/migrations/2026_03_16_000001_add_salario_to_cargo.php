<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('CARGO', 'CARGO_SALARIO')) {
            Schema::table('CARGO', function (Blueprint $table) {
                $table->decimal('CARGO_SALARIO', 12, 2)->nullable()->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('CARGO', 'CARGO_SALARIO')) {
            Schema::table('CARGO', function (Blueprint $table) {
                $table->dropColumn('CARGO_SALARIO');
            });
        }
    }
};
