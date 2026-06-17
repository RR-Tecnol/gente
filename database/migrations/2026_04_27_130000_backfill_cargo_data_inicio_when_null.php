<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CARGO_DATA_INICIO passa a ser exigido na API; registros legados sem data
 * recebem data simbólica (competência “pré-sistema”) para permitir checagem de vigência.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('CARGO') || ! Schema::hasColumn('CARGO', 'CARGO_DATA_INICIO')) {
            return;
        }
        DB::table('CARGO')
            ->whereNull('CARGO_DATA_INICIO')
            ->update(['CARGO_DATA_INICIO' => '2000-01-01']);
    }

    public function down(): void
    {
        // Não revertemos: não é possível distinguir linhas ajustadas de linhas nulas originais.
    }
};
