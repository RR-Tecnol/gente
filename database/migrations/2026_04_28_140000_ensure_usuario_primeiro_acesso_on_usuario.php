<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Garante USUARIO_PRIMEIRO_ACESSO (0/1 como boolean de negócio) para trava de primeiro acesso.
 * Bases legadas: pode já existir de AddMissingColumnsToUsuario; não duplica.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('USUARIO')) {
            return;
        }

        if (! Schema::hasColumn('USUARIO', 'USUARIO_PRIMEIRO_ACESSO')) {
            Schema::table('USUARIO', function (Blueprint $table) {
                $table->boolean('USUARIO_PRIMEIRO_ACESSO')->default(true);
            });
        }
    }

    public function down(): void
    {
        // não remove coluna em produção
    }
};
