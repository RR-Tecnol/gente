<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FuncionariosPMSLzSeeder — 18 funcionários de teste para o Sprint 3.
 * Sprint 3a: inclui CARREIRA_ID, FUNCIONARIO_CLASSE e FUNCIONARIO_REFERENCIA.
 */
class FuncionariosPMSLzSeeder extends Seeder
{
    public function run(): void
    {
        // Buscar vínculos pelo sigla
        $vinculos = DB::table('VINCULO')
            ->whereIn('VINCULO_SIGLA', ['EFT', 'SP', 'CC', 'CC-M2', 'FC', 'PSS', 'GM', 'PROF'])
            ->pluck('VINCULO_ID', 'VINCULO_SIGLA');

        $vEft = $vinculos['EFT'] ?? null;
        $vSP = $vinculos['SP'] ?? null;
        $vCC = $vinculos['CC'] ?? null;
        $vCCM2 = $vinculos['CC-M2'] ?? null;
        $vFC = $vinculos['FC'] ?? null;
        $vPSS = $vinculos['PSS'] ?? null;
        $vGM = $vinculos['GM'] ?? null;
        $vProf = $vinculos['PROF'] ?? null;

        // Buscar CARREIRA_IDs pelo nome (populados pelo TabelaSalarialPMSLzSeeder)
        $carrGeral = DB::table('CARREIRA')->where('CARREIRA_NOME', 'Servidores Efetivos Gerais')->value('CARREIRA_ID');
        $carrGuarda = DB::table('CARREIRA')->where('CARREIRA_NOME', 'Guarda Municipal')->value('CARREIRA_ID');
        $carrMag = DB::table('CARREIRA')->where('CARREIRA_NOME', 'Magistério Municipal')->value('CARREIRA_ID');

        // Buscar unidades para lotação
        $unidades = DB::table('UNIDADE')
            ->whereIn('UNIDADE_SIGLA', ['SEMAD', 'SEMFAZ', 'SEMUS', 'SEMED', 'SEMUSC', 'SEMIT', 'GABPREF', 'SEPLAN', 'SEMOSP', 'SEMCAS'])
            ->pluck('UNIDADE_ID', 'UNIDADE_SIGLA');

        // Setor padrão (primeiro de cada unidade)
        $getSetor = function ($sigla) use ($unidades) {
            $uid = $unidades[$sigla] ?? null;
            if (!$uid)
                return null;
            return DB::table('SETOR')->where('UNIDADE_ID', $uid)->value('SETOR_ID');
        };

        /*
         * [cpf, nome, sexo(1=M/2=F), nasc, admissao, vinculo_id, regime, matricula, unidade_sigla, classe, referencia, carreira_id]
         *
         * EFT → classe=V   ref=C  carrGeral
         * PROF → classe=PNM-II ref=B  carrMag
         * GM   → classe=GII  ref=A  carrGuarda
         * PSS/CC/SP/CCM2/FC → null/null/null
         */
        $funcionarios = [
            // EFT — Efetivos Gerais
            ['47026653038', 'Ana Cristina Barros', 2, '1985-03-12', '2010-02-01', $vEft, 'RPPS', '2010-0001', 'SEMAD', 'V', 'C', $carrGeral],
            ['65498732053', 'José Carlos Lima', 1, '1979-07-22', '2008-05-15', $vEft, 'RPPS', '2008-0002', 'SEMAD', 'V', 'C', $carrGeral],
            ['80342675091', 'Maria das Dores Silva', 2, '1972-11-05', '2005-03-01', $vEft, 'RPPS', '2005-0003', 'SEMFAZ', 'VII', 'D', $carrGeral],
            ['19157469006', 'Francisco Ramos Costa', 1, '1983-01-18', '2009-08-01', $vEft, 'RPPS', '2009-0004', 'SEMUS', 'V', 'C', $carrGeral],
            ['32489156017', 'Antônia Pereira Nunes', 2, '1988-06-30', '2012-01-02', $vEft, 'RPPS', '2012-0005', 'SEMUS', 'V', 'B', $carrGeral],
            ['94537218082', 'Cláudia Regina Santos', 2, '1984-08-09', '2010-07-01', $vEft, 'RPPS', '2010-0009', 'SEMIT', 'VI', 'C', $carrGeral],
            ['73459281073', 'Carlos Eduardo Brito', 1, '1970-05-20', '2003-03-01', $vEft, 'RPPS', '2003-0016', 'SEMFAZ', 'VIII', 'E', $carrGeral],
            ['84126793014', 'Benedita Araújo Lima', 2, '1978-08-07', '2006-04-01', $vEft, 'RPPS', '2006-0017', 'SEMUS', 'VI', 'D', $carrGeral],
            ['95834126050', 'Danielle Souza Cunha', 2, '1999-12-03', '2023-05-15', $vEft, 'RPPS', '2023-0018', 'SEMAD', 'I', 'A', $carrGeral],
            // PROF — Magistério Municipal
            ['56723481044', 'Raimundo Sousa Farias', 1, '1980-09-14', '2007-02-20', $vProf, 'RPPS', '2007-0006', 'SEMED', 'PNM-II', 'B', $carrMag],
            ['71264893025', 'Luciana Moura Castro', 2, '1986-04-03', '2011-03-15', $vProf, 'RPPS', '2011-0007', 'SEMED', 'PNM-II', 'C', $carrMag],
            // GM — Guarda Municipal
            ['83901527060', 'Pedro Henrique Alves', 1, '1990-10-21', '2015-01-05', $vGM, 'RPPS', '2015-0008', 'SEMUSC', 'GII', 'A', $carrGuarda],
            // SP — Serviço Prestado (RGPS, sem carreira)
            ['17390462030', 'Roberto Fonseca Melo', 1, '1965-12-15', '2000-05-01', $vSP, 'RGPS', '2000-0010', 'SEMAD', null, null, null],
            ['28105394077', 'Francisca Leal Pinto', 2, '1969-03-28', '2001-02-01', $vSP, 'RGPS', '2001-0011', 'SEMCAS', null, null, null],
            // CC — Cargo Comissionado (RGPS)
            ['39621875041', 'Geraldo Augusto Reis', 1, '1975-06-17', '2021-01-04', $vCC, 'RGPS', '2021-0012', 'GABPREF', null, null, null],
            ['40286753085', 'Silvana Monteiro Cruz', 2, '1982-09-02', '2013-03-01', $vCCM2, 'RPPS', '2013-0013', 'SEPLAN', null, null, null],
            // FC — Função Comissionada
            ['51903486006', 'Marcos Vinícius Neto', 1, '1981-11-11', '2012-06-01', $vFC, 'RPPS', '2012-0014', 'SEMOSP', null, null, null],
            // PSS — Processo Seletivo Simplificado (RGPS)
            ['62718354049', 'Ana Paula Ferreira', 2, '1995-02-25', '2024-01-08', $vPSS, 'RGPS', '2024-0015', 'SEMED', null, null, null],
        ];

        $colClasse = Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_CLASSE');
        $colRef = Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_REFERENCIA');
        $colCarr = Schema::hasColumn('FUNCIONARIO', 'CARREIRA_ID');

        $total = 0;
        $atualizados = 0;
        foreach ($funcionarios as [$cpf, $nome, $sexo, $nasc, $admissao, $vinId, $regime, $matr, $uSigla, $classe, $referencia, $carrId]) {

            // Se já existe — atualizar CLASSE/REFERENCIA/CARREIRA
            $funcExiste = DB::table('FUNCIONARIO')->where('FUNCIONARIO_MATRICULA', $matr)->first();
            if ($funcExiste) {
                $upd = [];
                if ($colClasse && $classe !== null)
                    $upd['FUNCIONARIO_CLASSE'] = $classe;
                if ($colRef && $referencia !== null)
                    $upd['FUNCIONARIO_REFERENCIA'] = $referencia;
                if ($colCarr && $carrId !== null)
                    $upd['CARREIRA_ID'] = $carrId;
                if ($upd) {
                    DB::table('FUNCIONARIO')->where('FUNCIONARIO_MATRICULA', $matr)->update($upd);
                    $atualizados++;
                }
                continue;
            }

            // PESSOA
            $pessoaId = DB::table('PESSOA')->where('PESSOA_CPF_NUMERO', $cpf)->value('PESSOA_ID');
            if (!$pessoaId) {
                $pessoaData = [
                    'PESSOA_NOME' => $nome,
                    'PESSOA_CPF_NUMERO' => $cpf,
                    'PESSOA_SEXO' => $sexo,
                    'PESSOA_DATA_NASCIMENTO' => $nasc,
                    'PESSOA_ATIVO' => 1,
                    'PESSOA_DATA_CADASTRO' => now()->toDateString(),
                ];
                if (Schema::hasColumn('PESSOA', 'PESSOA_CPF'))
                    $pessoaData['PESSOA_CPF'] = $cpf;
                if (Schema::hasColumn('PESSOA', 'PESSOA_NASC'))
                    $pessoaData['PESSOA_NASC'] = $nasc;
                if (Schema::hasColumn('PESSOA', 'PESSOA_DEPENDENTES_IRRF'))
                    $pessoaData['PESSOA_DEPENDENTES_IRRF'] = 0;
                $pessoaId = DB::table('PESSOA')->insertGetId($pessoaData);
            }

            // FUNCIONARIO
            $funcData = [
                'PESSOA_ID' => $pessoaId,
                'FUNCIONARIO_MATRICULA' => $matr,
                'FUNCIONARIO_DATA_INICIO' => $admissao,
                'FUNCIONARIO_ATIVO' => 1,
                'FUNCIONARIO_REGIME_PREV' => $regime,
                'FUNCIONARIO_DATA_CADASTRO' => now()->toDateString(),
                'FUNCIONARIO_DATA_ATUALIZACAO' => now()->toDateString(),
            ];
            if ($vinId && Schema::hasColumn('FUNCIONARIO', 'VINCULO_ID'))
                $funcData['VINCULO_ID'] = $vinId;
            if ($colClasse && $classe !== null)
                $funcData['FUNCIONARIO_CLASSE'] = $classe;
            if ($colRef && $referencia !== null)
                $funcData['FUNCIONARIO_REFERENCIA'] = $referencia;
            if ($colCarr && $carrId !== null)
                $funcData['CARREIRA_ID'] = $carrId;

            $funcId = DB::table('FUNCIONARIO')->insertGetId($funcData);

            // LOTACAO
            $setorId = $getSetor($uSigla);
            if ($setorId && $funcId) {
                $lotData = ['FUNCIONARIO_ID' => $funcId, 'SETOR_ID' => $setorId];
                if (Schema::hasColumn('LOTACAO', 'VINCULO_ID') && $vinId)
                    $lotData['VINCULO_ID'] = $vinId;
                DB::table('LOTACAO')->insert($lotData);
            }
            $total++;
        }

        $this->command->info("✅ FuncionariosPMSLzSeeder: {$total} inseridos, {$atualizados} atualizados (CLASSE/REF/CARREIRA).");
    }
}
