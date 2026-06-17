<?php

namespace Database\Seeders;

use App\Models\PccvDominio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Domínio jurídico canónico — PCCVs e estatutos da Prefeitura Municipal de São Luís.
 * Fonte oficial para vínculo em CARGO.PCCV_ID e para motor de folha / SISFOLHA.
 *
 * @see database/migrations/2026_04_28_210000_create_pccv_dominio_table.php
 */
class PccvDominioSeeder extends Seeder
{
    /** @return list<array{SIGLA: string, NOME_LEI: string, ATIVO: bool}> */
    private static function dominiosOficiais(): array
    {
        return [
            [
                'SIGLA' => 'MAGISTERIO',
                'NOME_LEI' => 'PCCV Magistério (Lei nº 4.928/2008) — Quadro do Magistério Municipal',
                'ATIVO' => true,
            ],
            [
                'SIGLA' => 'GERAL',
                'NOME_LEI' => 'Estatuto dos Servidores / Regime Geral (Lei nº 4.615/2006)',
                'ATIVO' => true,
            ],
            [
                'SIGLA' => 'SAUDE',
                'NOME_LEI' => 'PCCV Profissionais da Saúde (Lei nº 4.616/2006)',
                'ATIVO' => true,
            ],
            [
                'SIGLA' => 'SEGURANCA',
                'NOME_LEI' => 'PCCV Guarda Municipal — Lei Orgânica e plano específico da corporação',
                'ATIVO' => true,
            ],
        ];
    }

    public function run(): void
    {
        if (! Schema::hasTable('PCCV_DOMINIO')) {
            return;
        }

        foreach (self::dominiosOficiais() as $r) {
            PccvDominio::updateOrCreate(
                ['SIGLA' => $r['SIGLA']],
                [
                    'NOME_LEI' => $r['NOME_LEI'],
                    'ATIVO' => $r['ATIVO'],
                ]
            );
        }
    }
}
