<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Popula a tabela PERFIL com todos os perfis de acesso do sistema.
 * Compatível com SQLite/MySQL/PostgreSQL via updateOrInsert.
 */
class PerfilSeeder extends Seeder
{
    public function run(): void
    {
        $perfis = [
            [1, 'Desenvolvedor'],
            [2, 'Administrador'],
            [3, 'Operacional'],
            [4, 'Manutenção'],
            [5, 'Externo'],
            [6, 'RH Folha'],
            [7, 'Gestão'],
            [8, 'RH Unidade'],
            [9, 'Direitos e Deveres'],
            [10, 'Recrutador'],
            [11, 'Coordenador de Setor'],
            [12, 'Diretor / Gestor de Unidade'],
            [13, 'Equipe SISGEP'],
            [14, 'RH APS'],
            [15, 'RH Rede'],
        ];

        foreach ($perfis as [$id, $nome]) {
            DB::table('PERFIL')->updateOrInsert(
                ['PERFIL_ID' => $id],
                ['PERFIL_NOME' => $nome, 'PERFIL_ATIVO' => 1]
            );
        }
    }
}
