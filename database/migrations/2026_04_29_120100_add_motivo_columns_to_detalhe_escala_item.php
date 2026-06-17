<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('DETALHE_ESCALA_ITEM') || ! Schema::hasTable('MOTIVO_ALTERACAO_DOMINIO')) {
            return;
        }

        Schema::table('DETALHE_ESCALA_ITEM', function (Blueprint $table) {
            if (! Schema::hasColumn('DETALHE_ESCALA_ITEM', 'MOTIVO_ALTERACAO_ID')) {
                $table->unsignedInteger('MOTIVO_ALTERACAO_ID')->nullable();
            }
            if (! Schema::hasColumn('DETALHE_ESCALA_ITEM', 'DOCUMENTO_REFERENCIA')) {
                $table->string('DOCUMENTO_REFERENCIA', 200)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('DETALHE_ESCALA_ITEM')) {
            return;
        }

        Schema::table('DETALHE_ESCALA_ITEM', function (Blueprint $table) {
            if (Schema::hasColumn('DETALHE_ESCALA_ITEM', 'DOCUMENTO_REFERENCIA')) {
                $table->dropColumn('DOCUMENTO_REFERENCIA');
            }
            if (Schema::hasColumn('DETALHE_ESCALA_ITEM', 'MOTIVO_ALTERACAO_ID')) {
                $table->dropColumn('MOTIVO_ALTERACAO_ID');
            }
        });
    }
};
