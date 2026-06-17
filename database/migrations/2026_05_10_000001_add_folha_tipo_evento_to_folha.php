<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('FOLHA', function (Blueprint $table) {
            if (!Schema::hasColumn('FOLHA', 'FOLHA_TIPO_EVENTO')) {
                $table->string('FOLHA_TIPO_EVENTO', 30)
                    ->default('NORMAL')
                    ->after('FOLHA_DESCRICAO')
                    ->comment('NORMAL | DECIMO_TERCEIRO_1 | DECIMO_TERCEIRO_2 | RESCISORIO | FERIAS');
            }
        });
    }

    public function down(): void
    {
        Schema::table('FOLHA', function (Blueprint $table) {
            if (Schema::hasColumn('FOLHA', 'FOLHA_TIPO_EVENTO')) {
                $table->dropColumn('FOLHA_TIPO_EVENTO');
            }
        });
    }
};
