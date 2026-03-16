<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateConfiguracaoSistemaTable extends Migration
{
    public function up()
    {
        Schema::create('CONFIGURACAO_SISTEMA', function (Blueprint $table) {
            $table->id('CONFIG_ID');
            $table->string('CONFIG_CHAVE', 50)->unique();
            $table->string('CONFIG_VALOR', 255)->nullable();
            $table->string('CONFIG_DESCRICAO', 200)->nullable();
            $table->string('CONFIG_TIPO', 20)->default('BOOLEAN'); // BOOLEAN | TEXT | NUMBER
            $table->unsignedBigInteger('USUARIO_ID')->nullable();
            $table->dateTime('CONFIG_UPDATED_AT')->nullable();
        });

        // Seed: configurações padrão do sistema
        DB::table('CONFIGURACAO_SISTEMA')->insert([
            [
                'CONFIG_CHAVE' => 'MODULO_PONTO_ATIVO',
                'CONFIG_VALOR' => '0',
                'CONFIG_DESCRICAO' => 'Habilita o módulo de ponto eletrônico',
                'CONFIG_TIPO' => 'BOOLEAN',
                'USUARIO_ID' => null,
                'CONFIG_UPDATED_AT' => now(),
            ],
            [
                'CONFIG_CHAVE' => 'PONTO_HORAS_EXTRA_AUTOAPROVAR',
                'CONFIG_VALOR' => '2',
                'CONFIG_DESCRICAO' => 'Horas extras por dia até onde aprovar automaticamente (0 = desabilitar)',
                'CONFIG_TIPO' => 'NUMBER',
                'USUARIO_ID' => null,
                'CONFIG_UPDATED_AT' => now(),
            ],
            [
                'CONFIG_CHAVE' => 'MODULO_OSS_ATIVO',
                'CONFIG_VALOR' => '0',
                'CONFIG_DESCRICAO' => 'Habilita o módulo de Organizações Sociais de Saúde',
                'CONFIG_TIPO' => 'BOOLEAN',
                'USUARIO_ID' => null,
                'CONFIG_UPDATED_AT' => now(),
            ],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('CONFIGURACAO_SISTEMA');
    }
}
