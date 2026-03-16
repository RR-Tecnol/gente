<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Popula CONFIGURACAO_SISTEMA com os valores padrão do sistema.
 * Idempotente via updateOrInsert.
 */
class ConfiguracaoSistemaSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            [
                'CONFIG_CHAVE' => 'MODULO_PONTO_ATIVO',
                'CONFIG_VALOR' => '0',
                'CONFIG_DESCRICAO' => 'Habilita o módulo de Ponto Eletrônico no sistema.',
                'CONFIG_TIPO' => 'BOOLEAN',
            ],
            [
                'CONFIG_CHAVE' => 'MODULO_OSS_ATIVO',
                'CONFIG_VALOR' => '0',
                'CONFIG_DESCRICAO' => 'Habilita o módulo de Organizações Sociais de Saúde (OSS).',
                'CONFIG_TIPO' => 'BOOLEAN',
            ],
            [
                'CONFIG_CHAVE' => 'PONTO_HORAS_EXTRA_AUTOAPROVAR',
                'CONFIG_VALOR' => '2',
                'CONFIG_DESCRICAO' => 'Horas extras por dia que são aprovadas automaticamente ao fechar a apuração (0 = desabilitado).',
                'CONFIG_TIPO' => 'NUMBER',
            ],
            [
                'CONFIG_CHAVE' => 'SISTEMA_NOME',
                'CONFIG_VALOR' => 'GENTE',
                'CONFIG_DESCRICAO' => 'Nome do sistema exibido na interface.',
                'CONFIG_TIPO' => 'TEXT',
            ],
            [
                'CONFIG_CHAVE' => 'FERIAS_ALERTA_DIAS_CRITICO',
                'CONFIG_VALOR' => '60',
                'CONFIG_DESCRICAO' => 'Dias antes do vencimento para classificar férias como CRÍTICO (nível de alerta alto).',
                'CONFIG_TIPO' => 'NUMBER',
            ],
            [
                'CONFIG_CHAVE' => 'FERIAS_ALERTA_DIAS_ATENCAO',
                'CONFIG_VALOR' => '120',
                'CONFIG_DESCRICAO' => 'Dias antes do vencimento para classificar férias como ATENÇÃO (nível de alerta médio).',
                'CONFIG_TIPO' => 'NUMBER',
            ],
        ];

        foreach ($configs as $config) {
            DB::table('CONFIGURACAO_SISTEMA')->updateOrInsert(
                ['CONFIG_CHAVE' => $config['CONFIG_CHAVE']],
                $config
            );
        }
    }
}
