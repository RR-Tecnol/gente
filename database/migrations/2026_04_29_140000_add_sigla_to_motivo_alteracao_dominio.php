<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('MOTIVO_ALTERACAO_DOMINIO')) {
            return;
        }
        if (Schema::hasColumn('MOTIVO_ALTERACAO_DOMINIO', 'SIGLA')) {
            return;
        }

        Schema::table('MOTIVO_ALTERACAO_DOMINIO', function (Blueprint $table) {
            $table->string('SIGLA', 64)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('MOTIVO_ALTERACAO_DOMINIO') || ! Schema::hasColumn('MOTIVO_ALTERACAO_DOMINIO', 'SIGLA')) {
            return;
        }

        Schema::table('MOTIVO_ALTERACAO_DOMINIO', function (Blueprint $table) {
            $table->dropColumn('SIGLA');
        });
    }
};
