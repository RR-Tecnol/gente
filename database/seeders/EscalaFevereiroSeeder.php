<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EscalaFevereiroSeeder extends Seeder
{
    public function run(): void
    {
        $competencia = '02/2026';
        $ano = 2026;
        $mes = 2;
        $diasNoMes = 28;

        $turnosTrabalho = [
            ['sigla' => 'M', 'peso' => 35],
            ['sigla' => 'V', 'peso' => 25],
            ['sigla' => 'N', 'peso' => 15],
            ['sigla' => 'F', 'peso' => 20],
            ['sigla' => 'AT', 'peso' => 5],
        ];

        $turnosMedicos = [
            ['sigla' => 'M', 'peso' => 30],
            ['sigla' => 'T', 'peso' => 25],
            ['sigla' => 'N', 'peso' => 20],
            ['sigla' => 'P', 'peso' => 15],
            ['sigla' => 'F', 'peso' => 10],
        ];

        // Busca funcionários ativos com setor via LOTACAO (última lotação)
        $funcionariosComSetor = DB::table('FUNCIONARIO as F')
            ->leftJoin(DB::raw('(SELECT FUNCIONARIO_ID, SETOR_ID FROM LOTACAO
                WHERE LOTACAO_ID IN (
                    SELECT MAX(LOTACAO_ID) FROM LOTACAO GROUP BY FUNCIONARIO_ID
                )) as L'), 'F.FUNCIONARIO_ID', '=', 'L.FUNCIONARIO_ID')
            ->leftJoin('SETOR', 'L.SETOR_ID', '=', 'SETOR.SETOR_ID')
            ->where('F.FUNCIONARIO_ATIVO', 1)
            ->select(
                'F.FUNCIONARIO_ID',
                'L.SETOR_ID',
                DB::raw("COALESCE(SETOR.SETOR_NOME, 'Sem Setor') as SETOR_NOME")
            )
            ->get();

        if ($funcionariosComSetor->isEmpty()) {
            $this->command->warn('Nenhum funcionário ativo encontrado.');
            return;
        }

        // 1. Escala Geral de Trabalho (sem setor)
        $escalaGeral = DB::table('ESCALA')
            ->where('ESCALA_COMPETENCIA', $competencia)
            ->whereNull('SETOR_ID')
            ->first();

        $escalaGeralId = $escalaGeral
            ? $escalaGeral->ESCALA_ID
            : DB::table('ESCALA')->insertGetId([
                'ESCALA_COMPETENCIA' => $competencia,
                'SETOR_ID' => null,
                'ESCALA_STATUS' => 'Fechada',
                'ESCALA_OBSERVACAO' => 'Escala geral – demonstração',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        foreach ($funcionariosComSetor as $func) {
            $dt = DB::table('DETALHE_ESCALA')
                ->where('ESCALA_ID', $escalaGeralId)
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->first();

            $dtId = $dt
                ? $dt->DETALHE_ESCALA_ID
                : DB::table('DETALHE_ESCALA')->insertGetId([
                    'ESCALA_ID' => $escalaGeralId,
                    'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('DETALHE_ESCALA_ITEM')->where('DETALHE_ESCALA_ID', $dtId)->delete();
            DB::table('DETALHE_ESCALA_ITEM')->insert(
                $this->gerarItens($dtId, $ano, $mes, $diasNoMes, $turnosTrabalho)
            );
        }

        // 2. Escalas Médicas por Setor
        $setores = $funcionariosComSetor
            ->filter(fn($f) => $f->SETOR_ID !== null)
            ->groupBy('SETOR_ID');

        foreach ($setores as $setorId => $funcsDoSetor) {
            $em = DB::table('ESCALA')
                ->where('ESCALA_COMPETENCIA', $competencia)
                ->where('SETOR_ID', $setorId)
                ->first();

            $emId = $em
                ? $em->ESCALA_ID
                : DB::table('ESCALA')->insertGetId([
                    'ESCALA_COMPETENCIA' => $competencia,
                    'SETOR_ID' => $setorId,
                    'ESCALA_STATUS' => 'Fechada',
                    'ESCALA_OBSERVACAO' => 'Escala médica – demonstração',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            foreach ($funcsDoSetor as $func) {
                $dt = DB::table('DETALHE_ESCALA')
                    ->where('ESCALA_ID', $emId)
                    ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                    ->first();

                $dtId = $dt
                    ? $dt->DETALHE_ESCALA_ID
                    : DB::table('DETALHE_ESCALA')->insertGetId([
                        'ESCALA_ID' => $emId,
                        'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                DB::table('DETALHE_ESCALA_ITEM')->where('DETALHE_ESCALA_ID', $dtId)->delete();
                DB::table('DETALHE_ESCALA_ITEM')->insert(
                    $this->gerarItens($dtId, $ano, $mes, $diasNoMes, $turnosMedicos)
                );
            }
        }

        $this->command->info(sprintf(
            '✅ Escala %s populada: %d funcionários em %d setores.',
            $competencia,
            $funcionariosComSetor->count(),
            $setores->count()
        ));
    }

    private function gerarItens(int $detalheId, int $ano, int $mes, int $dias, array $turnos): array
    {
        $itens = [];
        for ($dia = 1; $dia <= $dias; $dia++) {
            $dow = (int) date('w', mktime(0, 0, 0, $mes, $dia, $ano));
            $fimSem = ($dow === 0 || $dow === 6);
            $sigla = $fimSem && rand(1, 100) <= 65 ? 'F' : $this->sorteio($turnos, $fimSem ? ['F'] : []);

            $itens[] = [
                'DETALHE_ESCALA_ID' => $detalheId,
                'DETALHE_ESCALA_ITEM_DATA' => sprintf('%04d-%02d-%02d', $ano, $mes, $dia),
                'TURNO_SIGLA' => $sigla,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        return $itens;
    }

    private function sorteio(array $turnos, array $excluir = []): string
    {
        $filtrados = array_values(array_filter($turnos, fn($t) => !in_array($t['sigla'], $excluir)));
        $totalPeso = array_sum(array_column($filtrados, 'peso'));
        $rand = rand(1, max($totalPeso, 1));
        $acum = 0;
        foreach ($filtrados as $t) {
            $acum += $t['peso'];
            if ($rand <= $acum)
                return $t['sigla'];
        }
        return $filtrados[0]['sigla'] ?? 'M';
    }
}
