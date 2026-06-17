<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga o cargo ao PCCV de referência (folha, progressão, integrações).
 * Nullable: legado sem classificação continua válido.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('CARGO') || !Schema::hasTable('PCCV_DOMINIO')) {
            return;
        }

        if (Schema::hasColumn('CARGO', 'PCCV_ID')) {
            return;
        }

        Schema::table('CARGO', function (Blueprint $table) {
            $table->unsignedInteger('PCCV_ID')->nullable();
        });

        Schema::table('CARGO', function (Blueprint $table) {
            $table->foreign('PCCV_ID')
                ->references('PCCV_DOMINIO_ID')
                ->on('PCCV_DOMINIO');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('CARGO') || !Schema::hasColumn('CARGO', 'PCCV_ID')) {
            return;
        }

        Schema::table('CARGO', function (Blueprint $table) {
            $table->dropForeign(['PCCV_ID']);
        });

        Schema::table('CARGO', function (Blueprint $table) {
            $table->dropColumn('PCCV_ID');
        });
    }
};
