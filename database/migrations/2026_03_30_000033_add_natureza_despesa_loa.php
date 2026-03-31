<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ORCAMENTO_LOA', function (Blueprint $table) {
            if (!Schema::hasColumn('ORCAMENTO_LOA', 'LOA_NATUREZA_DESPESA')) {
                // ex: 3.1.90.11 — elemento de despesa (PCASP)
                $table->string('LOA_NATUREZA_DESPESA', 20)->nullable()->after('LOA_ANO');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ORCAMENTO_LOA', function (Blueprint $table) {
            $table->dropColumn('LOA_NATUREZA_DESPESA');
        });
    }
};
