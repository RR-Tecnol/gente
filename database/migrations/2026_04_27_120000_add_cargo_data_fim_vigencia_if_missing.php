<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('CARGO')) {
            return;
        }
        if (!Schema::hasColumn('CARGO', 'CARGO_DATA_FIM')) {
            Schema::table('CARGO', function (Blueprint $table) {
                $table->date('CARGO_DATA_FIM')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('CARGO') && Schema::hasColumn('CARGO', 'CARGO_DATA_FIM')) {
            Schema::table('CARGO', function (Blueprint $table) {
                $table->dropColumn('CARGO_DATA_FIM');
            });
        }
    }
};
