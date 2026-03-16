<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

/**
 * FevereiroDemoSeeder — v3
 *
 * Usa setores e vínculos JÁ EXISTENTES no banco.
 * Popula: lotações, pontos, escala, folha de Fev/2026.
 */
class FevereiroDemoSeeder extends Seeder
{
    private array $salariosCargo = [
        'Médico Clínico' => 8500.00,
        'Enfermeiro(a)' => 4200.00,
        'Técnico de Enfermagem' => 2800.00,
        'Assistente Administrativo' => 2200.00,
        'Analista de RH' => 3500.00,
    ];

    public function run(): void
    {
        // ── 0. Funcionários não-admin ────────────────────────────────────
        $funcionarios = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->where('f.FUNCIONARIO_ID', '>=', 4)
            ->select('f.FUNCIONARIO_ID', 'p.PESSOA_NOME')
            ->limit(15)
            ->get();

        if ($funcionarios->isEmpty()) {
            $this->command->error('Nenhum funcionário encontrado (ID >= 4).');
            return;
        }
        $this->command->info("✓ {$funcionarios->count()} funcionários encontrados.");

        // ── 1. Setores existentes ────────────────────────────────────────
        $this->command->info('→ Buscando setores existentes...');
        $setores = DB::table('SETOR')->where('SETOR_ATIVO', 1)->get();
        if ($setores->isEmpty()) {
            $this->command->error('Nenhum setor ativo encontrado no banco. Cadastre setores primeiro.');
            return;
        }
        $setorList = $setores->take(5)->values(); // limita a 5
        foreach ($setorList as $s) {
            $this->command->line("  · Setor: [{$s->SETOR_ID}] {$s->SETOR_NOME}");
        }

        // ── 2. Cargos (reutiliza ou cria com VINCULO_ID) ─────────────────
        $this->command->info('→ Cargos...');
        $vinculoId = DB::table('VINCULO')->value('VINCULO_ID') ?? 1;
        $cargoNomes = array_keys($this->salariosCargo);
        $cargoIds = [];
        foreach ($cargoNomes as $nome) {
            $row = DB::table('CARGO')->where('CARGO_NOME', $nome)->first();
            if ($row) {
                $cargoIds[$nome] = $row->CARGO_ID;
            } else {
                try {
                    $cargoIds[$nome] = DB::table('CARGO')->insertGetId(['CARGO_NOME' => $nome, 'CARGO_ATIVO' => 1]);
                } catch (\Exception $e) {
                    // Se falhar por falta de campo, ignora e usa primeiro cargo existente
                    $cargoIds[$nome] = DB::table('CARGO')->value('CARGO_ID') ?? 1;
                }
            }
            $this->command->line("  · Cargo {$nome}: [{$cargoIds[$nome]}]");
        }

        // ── 3. Lotações (distribui funcionários pelos setores existentes) ─
        $this->command->info('→ Lotações Fev/2026...');
        $totalSetores = $setorList->count();

        foreach ($funcionarios as $i => $func) {
            $setor = $setorList[$i % $totalSetores];
            $cargoNome = $cargoNomes[$i % count($cargoNomes)];

            // Fecha lotação ativa anterior
            DB::table('LOTACAO')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->whereNull('LOTACAO_DATA_FIM')
                ->update(['LOTACAO_DATA_FIM' => '2026-01-31']);

            DB::table('LOTACAO')->insert([
                'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID,
                'SETOR_ID' => $setor->SETOR_ID,
                'LOTACAO_DATA_INICIO' => '2026-02-01',
                'LOTACAO_DATA_FIM' => null,
                'VINCULO_ID' => $vinculoId,
            ]);
            $this->command->line("  · {$func->PESSOA_NOME} → [{$setor->SETOR_ID}] {$setor->SETOR_NOME} | {$cargoNome}");
        }

        // ── 4. Registros de Ponto (Fev/2026) ────────────────────────────
        $this->command->info('→ Batidas de ponto Fev/2026...');
        DB::table('REGISTRO_PONTO')
            ->whereIn('FUNCIONARIO_ID', $funcionarios->pluck('FUNCIONARIO_ID'))
            ->whereBetween('REGISTRO_DATA_HORA', ['2026-02-01 00:00:00', '2026-02-28 23:59:59'])
            ->delete();

        $padroes = [
            [['08', '00'], ['12', '00'], ['13', '00'], ['17', '00']],
            [['07', '30'], ['12', '00'], ['13', '00'], ['17', '30']],
            [['08', '05'], ['12', '05'], ['13', '05'], ['17', '05']],
            [['07', '55'], ['11', '55'], ['12', '55'], ['17', '00']],
        ];
        $tiposSeq = ['entrada', 'saida_almoco', 'retorno_almoco', 'saida'];

        foreach ($funcionarios as $idx => $func) {
            $pad = $padroes[$idx % count($padroes)];
            for ($dia = 1; $dia <= 28; $dia++) {
                $dt = Carbon::create(2026, 2, $dia);
                if ($dt->dayOfWeek === 0 || $dt->dayOfWeek === 6)
                    continue;
                if (rand(1, 15) === 1)
                    continue; // ~6% falta

                foreach ($tiposSeq as $ti => $tipo) {
                    [$h, $m] = $pad[$ti];
                    $minV = (int) $m + rand(-3, 3);
                    $hrV = (int) $h;
                    if ($minV < 0) {
                        $hrV--;
                        $minV += 60;
                    }
                    if ($minV >= 60) {
                        $hrV++;
                        $minV -= 60;
                    }

                    DB::table('REGISTRO_PONTO')->insert([
                        'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID,
                        'REGISTRO_DATA_HORA' => sprintf('2026-02-%02d %02d:%02d:00', $dia, $hrV, $minV),
                        'REGISTRO_TIPO' => $tipo,
                        'REGISTRO_ORIGEM' => 'DEMO',
                    ]);
                }
            }
            $this->command->line("  · Ponto {$func->PESSOA_NOME} ✓");
        }

        // ── 5. Escala médica (usa até 2 setores existentes) ──────────────
        $this->command->info('→ Escalas Fev/2026...');
        if (Schema::hasTable('ESCALA') && Schema::hasTable('DETALHE_ESCALA')) {
            foreach ($setorList->take(2) as $setor) {
                $escalaId = DB::table('ESCALA')
                    ->where('SETOR_ID', $setor->SETOR_ID)
                    ->where('ESCALA_COMPETENCIA', 'Fev/2026')
                    ->value('ESCALA_ID');

                if (!$escalaId) {
                    $escInsert = [
                        'SETOR_ID' => $setor->SETOR_ID,
                        'ESCALA_COMPETENCIA' => 'Fev/2026',
                        'ESCALA_DESCRICAO' => "Escala {$setor->SETOR_NOME} – Fev/2026",
                        'ESCALA_ATIVO' => 1,
                    ];
                    // TIPO_ESCALA_ID pode ser obrigatório — tenta sem, captura
                    try {
                        $escalaId = DB::table('ESCALA')->insertGetId($escInsert);
                    } catch (\Exception $e) {
                        $tipoEscalaId = DB::table('TIPO_ESCALA')->value('TIPO_ESCALA_ID') ?? 1;
                        $escalaId = DB::table('ESCALA')->insertGetId(array_merge($escInsert, ['TIPO_ESCALA_ID' => $tipoEscalaId]));
                    }
                }

                $funcsSetor = DB::table('LOTACAO')
                    ->where('SETOR_ID', $setor->SETOR_ID)
                    ->whereNull('LOTACAO_DATA_FIM')
                    ->pluck('FUNCIONARIO_ID');

                foreach ($funcsSetor as $fId) {
                    $detId = DB::table('DETALHE_ESCALA')
                        ->where('ESCALA_ID', $escalaId)
                        ->where('FUNCIONARIO_ID', $fId)
                        ->value('DETALHE_ESCALA_ID');

                    if (!$detId) {
                        $detId = DB::table('DETALHE_ESCALA')->insertGetId([
                            'ESCALA_ID' => $escalaId,
                            'FUNCIONARIO_ID' => $fId,
                        ]);
                    }

                    if (Schema::hasTable('DETALHE_ESCALA_ITEM')) {
                        $turnoCiclo = [1, 2, 3, 4];
                        $ci = 0;
                        for ($dia = 1; $dia <= 28; $dia++) {
                            $dt = Carbon::create(2026, 2, $dia);
                            if ($dt->dayOfWeek === 0 || $dt->dayOfWeek === 6)
                                continue;
                            DB::table('DETALHE_ESCALA_ITEM')->updateOrInsert(
                                ['DETALHE_ESCALA_ID' => $detId, 'DETALHE_ESCALA_ITEM_DATA' => $dt->toDateString()],
                                ['TURNO_ID' => $turnoCiclo[$ci++ % 4]]
                            );
                        }
                    }
                }
                $this->command->line("  · Escala [{$setor->SETOR_NOME}] ID: {$escalaId} ✓");
            }
        }

        // ── 6. Folha de pagamento Fev/2026 ──────────────────────────────
        $this->command->info('→ Folha Fev/2026...');
        $folhaId = DB::table('FOLHA')->where('FOLHA_COMPETENCIA', '2026-02')->value('FOLHA_ID');

        if (!$folhaId) {
            $folhaInsert = [
                'SETOR_ID' => $setorList->first()->SETOR_ID,
                'FOLHA_COMPETENCIA' => '2026-02',
                'FOLHA_STATUS' => 'Fechada',
                'FOLHA_ATIVO' => 1,
                'FOLHA_DESCRICAO' => 'Folha Fev/2026 – DEMO',
                'FOLHA_TIPO' => 'Mensal',
                'FOLHA_QTD_SERVIDORES' => $funcionarios->count(),
                'VINCULO_ID' => $vinculoId,
            ];
            try {
                $folhaId = DB::table('FOLHA')->insertGetId($folhaInsert);
            } catch (\Exception $e) {
                // Tenta sem VINCULO_ID se der erro
                unset($folhaInsert['VINCULO_ID']);
                $folhaId = DB::table('FOLHA')->insertGetId($folhaInsert);
            }
            $this->command->line("  · Folha criada ID: {$folhaId}");
        } else {
            $this->command->line("  · Folha já existe ID: {$folhaId}");
        }

        $totalP = 0;
        foreach ($funcionarios as $i => $func) {
            $cargoNome = $cargoNomes[$i % count($cargoNomes)];
            $salario = $this->salariosCargo[$cargoNome];
            $inss = round($salario * 0.11, 2);
            $irrf = $salario > 4664.68 ? round(($salario - 4664.68) * 0.275 + 345.61, 2) : 0.0;

            DB::table('DETALHE_FOLHA')->updateOrInsert(
                ['FOLHA_ID' => $folhaId, 'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID],
                ['DETALHE_FOLHA_PROVENTOS' => $salario, 'DETALHE_FOLHA_DESCONTOS' => $inss + $irrf]
            );
            $totalP += $salario;
            $this->command->line(sprintf(
                '  · %-30s R$ %8s   INSS R$ %6s   IRRF R$ %6s',
                $func->PESSOA_NOME,
                number_format($salario, 2, ',', '.'),
                number_format($inss, 2, ',', '.'),
                number_format($irrf, 2, ',', '.')
            ));
        }

        DB::table('FOLHA')->where('FOLHA_ID', $folhaId)->update([
            'FOLHA_VALOR_TOTAL' => $totalP,
            'FOLHA_QTD_SERVIDORES' => $funcionarios->count(),
        ]);

        // ── Resumo ───────────────────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('✅ Seed concluído!');
        $this->command->table(['Item', 'Resultado'], [
            ['Setores usados', $setorList->count()],
            ['Funcionários', $funcionarios->count()],
            ['Pontos registrados', 'OK (Fev/2026)'],
            ['Escalas', min(2, $setorList->count())],
            ['Folha ID', $folhaId],
            ['Total proventos', 'R$ ' . number_format($totalP, 2, ',', '.')],
        ]);
    }
}
