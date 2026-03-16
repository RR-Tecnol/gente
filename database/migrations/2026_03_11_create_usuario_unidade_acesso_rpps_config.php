<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SEC-08 / ARQ-03 — Controle de acesso por unidade
// Permite restringir operadores RH apenas a servidores de suas secretarias

return new class extends Migration {
    public function up()
    {
        // Tabela de vínculo usuário ↔ unidade autorizada
        if (!Schema::hasTable('USUARIO_UNIDADE_ACESSO')) {
            Schema::create('USUARIO_UNIDADE_ACESSO', function (Blueprint $table) {
                $table->increments('ACESSO_ID');
                $table->unsignedInteger('USUARIO_ID');
                $table->unsignedInteger('UNIDADE_ID');
                // TOTAL = acesso a todas as unidades (admin global)
                // UNIDADE = apenas a unidade listada
                $table->string('NIVEL_ACESSO', 20)->default('UNIDADE');
                $table->timestamps();

                $table->unique(['USUARIO_ID', 'UNIDADE_ID'], 'uq_usuario_unidade');
                $table->index('USUARIO_ID', 'idx_uua_usuario');
            });
        }

        // RPPS_CONFIG — alíquotas dinâmicas (PERF-03)
        if (!Schema::hasTable('RPPS_CONFIG')) {
            Schema::create('RPPS_CONFIG', function (Blueprint $table) {
                $table->increments('CONFIG_ID');
                $table->decimal('ALIQUOTA_SERVIDOR', 5, 2)->default(14.00);
                $table->decimal('ALIQUOTA_PATRONAL', 5, 2)->default(28.00);
                $table->string('VIGENCIA_INICIO', 7); // YYYY-MM
                $table->string('VIGENCIA_FIM', 7)->nullable();
                $table->text('OBSERVACAO')->nullable();
                $table->timestamps();
            });

            // Seed inicial com valores vigentes
            \Illuminate\Support\Facades\DB::table('RPPS_CONFIG')->insert([
                'ALIQUOTA_SERVIDOR' => 14.00,
                'ALIQUOTA_PATRONAL' => 28.00,
                'VIGENCIA_INICIO' => '2024-01',
                'VIGENCIA_FIM' => null,
                'OBSERVACAO' => 'Alíquotas padrão — ajustar conforme portaria vigente',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('USUARIO_UNIDADE_ACESSO');
        Schema::dropIfExists('RPPS_CONFIG');
    }
};
