<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Popula a tabela PERFIL com todos os perfis de acesso do sistema.
 * Usa DB::unprepared() dentro de uma transação para manter o contexto
 * do IDENTITY_INSERT na mesma conexão SQL Server.
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

        $sql = "SET IDENTITY_INSERT PERFIL ON;\n";
        foreach ($perfis as [$id, $nome]) {
            $nomeEsc = str_replace("'", "''", $nome);
            $sql .= "
                IF NOT EXISTS (SELECT 1 FROM PERFIL WHERE PERFIL_ID = {$id})
                    INSERT INTO PERFIL (PERFIL_ID, PERFIL_NOME, PERFIL_ATIVO) VALUES ({$id}, N'{$nomeEsc}', 1)
                ELSE
                    UPDATE PERFIL SET PERFIL_NOME = N'{$nomeEsc}', PERFIL_ATIVO = 1 WHERE PERFIL_ID = {$id};
            ";
        }
        $sql .= "SET IDENTITY_INSERT PERFIL OFF;";

        DB::unprepared($sql);
    }
}
