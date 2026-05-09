<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * PocPmslDemoFolhaSeeder — Seeder de demonstração end-to-end do motor de folha.
 *
 * OBJETIVO:
 *   Popular a base com 12 servidores fictícios da SEMED em diferentes cenários
 *   (RPPS / RGPS, com e sem faltas, com e sem afastamentos), gerar APURACAO_PONTO
 *   das competências Janeiro/2026 e Fevereiro/2026, e criar 2 folhas (Fev/2026
 *   e Mar/2026) prontas para serem processadas pelo MotorFolhaService.
 *
 * COMPORTAMENTO ESPERADO APÓS RODAR:
 *   1. Folha Fev/2026 ao ser processada lê APURACAO_PONTO Janeiro/2026
 *      → InclusaoFaltasService gera LANCAMENTO_FOLHA tipo 'D' para servidores
 *        com APURACAO_HORAS_FALTA > 0
 *   2. Folha Mar/2026 ao ser processada lê APURACAO_PONTO Fevereiro/2026
 *      → idem, mas com base em mês de 28 dias (denominador da pró-rata diferente)
 *
 * INVOCAÇÃO:
 *   php artisan db:seed --class=PocPmslDemoFolhaSeeder
 *
 * IDEMPOTÊNCIA:
 *   Usa updateOrInsert por chaves naturais (CPF, NOME). Re-execução não duplica.
 *   PIIs (CPFs) usam padrão "999.000.0NN-XX" para garantir não-colisão com reais.
 *
 * SEM EFEITOS DESTRUTIVOS:
 *   Não deleta nem altera dados existentes — apenas insere/atualiza.
 *
 * NÃO RODAR EM PRODUÇÃO REAL.
 *   Esse seeder é exclusivamente para validação pré-PoC do motor.
 */
class PocPmslDemoFolhaSeeder extends Seeder
{
    /** Prefixo dos CPFs sintéticos (faixa "999" não atribuível pela Receita). */
    private const CPF_PREFIX = '99900';

    /** Prefixo dos nomes para fácil identificação posterior. */
    private const NOME_PREFIX = 'DEMO POC';

    /** Janela de teste: APURACAO em Jan e Fev → folha em Fev e Mar */
    private const COMP_APURACAO_1 = '2026-01';  // Janeiro
    private const COMP_APURACAO_2 = '2026-02';  // Fevereiro
    private const COMP_FOLHA_1    = '202602';   // Folha Fev/2026 (lê apuração Jan)
    private const COMP_FOLHA_2    = '202603';   // Folha Mar/2026 (lê apuração Fev)

    /** Jornada padrão de 8h/dia para conversão hora→dia */
    private const HORAS_DIA = 8.0;

    public function run(): void
    {
        $this->command->info('═══════════════════════════════════════════════════════════════');
        $this->command->info('PocPmslDemoFolhaSeeder — popular dados de demonstração end-to-end');
        $this->command->info('═══════════════════════════════════════════════════════════════');

        DB::transaction(function () {
            $ctx = $this->prepararContexto();
            $this->command->info("Contexto OK — Setor SEMED RH = {$ctx['setor_id']}");

            $servidores = $this->definirServidores();
            $this->command->info('Definidos ' . count($servidores) . ' servidores demo');

            $funcMap = []; // [demo_codigo => FUNCIONARIO_ID]

            foreach ($servidores as $s) {
                $pessoaId = $this->upsertPessoa($s);
                $usuarioId = $this->upsertUsuario($s, $pessoaId);
                $funcId = $this->upsertFuncionario($s, $pessoaId, $usuarioId, $ctx);
                $lotacaoId = $this->upsertLotacao($funcId, $s, $ctx);
                $this->upsertAtribuicaoLotacao($lotacaoId, $s, $ctx);

                $funcMap[$s['codigo']] = $funcId;
            }

            $this->command->info('Funcionários OK: ' . count($funcMap));

            // Apuração de Ponto — Janeiro/2026 e Fevereiro/2026
            $this->popularApuracoes($funcMap, $servidores, $ctx);

            // Afastamentos (atestados, licenças)
            $this->popularAfastamentos($funcMap, $servidores, $ctx);

            // Adicionais permanentes — Camada 2 do motor (gratificações de função, regência, etc.)
            $this->popularAdicionais($funcMap, $servidores, $ctx);

            // Folhas Fev/2026 e Mar/2026 (ambas vinculadas ao vínculo "Estatutário Efetivo"
            // — mas o motor processa todos os funcionários ativos do lote)
            $this->criarFolhas($ctx);

            $this->command->info('═══════════════════════════════════════════════════════════════');
            $this->command->info('✅ DEMO POC POPULADA — pronta para processar folhas:');
            $this->command->info("   - Folha 1: " . self::COMP_FOLHA_1 . " (Fev/2026, lê APURACAO " . self::COMP_APURACAO_1 . ")");
            $this->command->info("   - Folha 2: " . self::COMP_FOLHA_2 . " (Mar/2026, lê APURACAO " . self::COMP_APURACAO_2 . ")");
            $this->command->info('═══════════════════════════════════════════════════════════════');
            $this->command->info('Para processar:');
            $this->command->info('   php artisan tinker');
            $this->command->info('   >>> app(\App\Services\MotorFolhaService::class)->calcularFolha(<FOLHA_ID>);');
            $this->command->info('═══════════════════════════════════════════════════════════════');
        });
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Contexto: resolve IDs de carreira, vínculo, setor, atribuição
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @return array{setor_id:int, vinc_efetivo:int, vinc_comissao:int, vinc_fc:int,
     *               carr_geral:int, carr_magisterio:int,
     *               atribuicoes_map:array<string,int>, cargos_map:array<string,int>,
     *               escolaridade_id:?int}
     */
    private function prepararContexto(): array
    {
        // 1. Setor SEMED — Superintendência de RH (já existe via OrganogramaPMSLzSeeder)
        $setor = DB::table('SETOR as s')
            ->join('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
            ->where('s.SETOR_NOME', 'Superintendência de Recursos Humanos')
            ->where(function ($q) {
                // Buscar SEMED por sigla (se coluna existir) ou por nome
                if (Schema::hasColumn('UNIDADE', 'UNIDADE_SIGLA')) {
                    $q->where('u.UNIDADE_SIGLA', 'SEMED');
                } else {
                    $q->where('u.UNIDADE_NOME', 'like', '%Educa%');
                }
            })
            ->select('s.SETOR_ID', 's.UNIDADE_ID')
            ->first();

        if (! $setor) {
            throw new \RuntimeException('Setor "Superintendência de Recursos Humanos" da SEMED não encontrado. Rode OrganogramaPMSLzSeeder antes.');
        }

        // 2. Vínculos (resolução por NOME — VINCULO usa VINCULO_NOME)
        $vincEfetivo  = $this->resolverVinculo('Estatutário Efetivo');
        $vincComissao = $this->resolverVinculo('Cargo em Comissão — Puro');
        $vincFc       = $this->resolverVinculo('Função de Confiança / FG');

        // 3. Carreiras — resolver por NOME
        $carrGeral      = $this->resolverCarreira('Servidores Efetivos Gerais');
        $carrMagisterio = $this->resolverCarreira('Magistério Municipal');

        // 4. ATRIBUICOES — criar 6 atribuições distintas pra demo
        $atribuicoesMap = $this->upsertAtribuicoesDemo();

        // 5. CARGOs — criar 5 cargos estáticos (efetivos não usam CARGO_SALARIO; CCs sim)
        $cargosMap = $this->upsertCargosDemo();

        // 6. FUNCOES — popular tabela FUNCAO descritivamente (sem FK no schema atual)
        $this->popularFuncoes();

        // 7. Escolaridade SUPERIOR (TABELA_GENERICA RTG::ESCOLARIDADE = 1)
        $colDesc = $this->resolverColDescTabelaGenerica();
        $escolaridadeId = null;
        if ($colDesc) {
            $escolaridadeId = DB::table('TABELA_GENERICA')
                ->where('TABELA_ID', 1)
                ->where($colDesc, 'like', '%SUPERIOR%')
                ->where('COLUNA_ID', '!=', 0)
                ->value('COLUNA_ID');
        }

        return [
            'setor_id'        => (int) $setor->SETOR_ID,
            'unidade_id'      => (int) $setor->UNIDADE_ID,
            'vinc_efetivo'    => $vincEfetivo,
            'vinc_comissao'   => $vincComissao,
            'vinc_fc'         => $vincFc,
            'carr_geral'      => $carrGeral,
            'carr_magisterio' => $carrMagisterio,
            'atribuicoes_map' => $atribuicoesMap,
            'cargos_map'      => $cargosMap,
            'escolaridade_id' => $escolaridadeId ? (int) $escolaridadeId : null,
        ];
    }

    private function resolverVinculo(string $nome): int
    {
        $id = DB::table('VINCULO')->where('VINCULO_NOME', $nome)->value('VINCULO_ID');
        if (! $id) {
            throw new \RuntimeException("Vínculo '{$nome}' não encontrado. Rode VinculosPMSLzSeeder antes.");
        }
        return (int) $id;
    }

    private function resolverCarreira(string $nome): int
    {
        $id = DB::table('CARREIRA')->where('CARREIRA_NOME', $nome)->value('CARREIRA_ID');
        if (! $id) {
            throw new \RuntimeException("Carreira '{$nome}' não encontrada. Rode TabelaSalarialPMSLzSeeder antes.");
        }
        return (int) $id;
    }

    /**
     * Cria 6 ATRIBUICOES distintas (idempotente). Retorna mapa codigo => ATRIBUICAO_ID.
     *
     * @return array<string,int>
     */
    private function upsertAtribuicoesDemo(): array
    {
        // [codigo, ATRIBUICAO_NOME, ATRIBUICAO_SIGLA]
        $atribs = [
            ['DEMO-PROF',   'DEMO POC PROFESSOR',                 'D-PROF'],
            ['DEMO-TEC',    'DEMO POC TECNICO ADMINISTRATIVO',    'D-TEC'],
            ['DEMO-COORD',  'DEMO POC COORDENADORA',              'D-CRD'],
            ['DEMO-ASSESS', 'DEMO POC ASSESSOR',                  'D-ASS'],
            ['DEMO-DIR',    'DEMO POC DIRETOR ESCOLAR',           'D-DIR'],
            ['DEMO-VDIR',   'DEMO POC VICE-DIRETOR ESCOLAR',      'D-VDIR'],
        ];

        $map = [];
        foreach ($atribs as [$cod, $nome, $sigla]) {
            $existente = DB::table('ATRIBUICAO')->where('ATRIBUICAO_NOME', $nome)->value('ATRIBUICAO_ID');

            $data = [
                'ATRIBUICAO_NOME'  => $nome,
                'ATRIBUICAO_SIGLA' => $sigla,
                'ATRIBUICAO_ATIVA' => 1,
            ];
            if (Schema::hasColumn('ATRIBUICAO', 'ATRIBUICAO_GESTAO')) {
                $data['ATRIBUICAO_GESTAO'] = 0;
            }

            if ($existente) {
                DB::table('ATRIBUICAO')->where('ATRIBUICAO_ID', $existente)->update($data);
                $map[$cod] = (int) $existente;
            } else {
                $map[$cod] = (int) DB::table('ATRIBUICAO')->insertGetId($data);
            }
        }

        return $map;
    }

    /**
     * Cria 7 CARGOs distintos (idempotente). Retorna mapa codigo => CARGO_ID.
     *
     * Cargos efetivos: CARGO_SALARIO=0 (vencimento vem da TABELA_SALARIAL via carreira+classe+ref).
     * Cargos CCs:      CARGO_SALARIO=valor (motor cai no fallback CARGO_SALARIO porque sem carreira).
     *
     * @return array<string,int>
     */
    private function upsertCargosDemo(): array
    {
        if (! Schema::hasTable('CARGO')) {
            return [];
        }

        // [codigo, CARGO_NOME, CARGO_SIGLA, CARGO_SALARIO_default]
        $cargos = [
            ['PROF-EB',    'PROFESSOR DE EDUCACAO BASICA',     'PROF-EB',    0],
            ['TEC-ADM',    'TECNICO ADMINISTRATIVO',           'TEC-ADM',    0],
            ['TEC-ADM-PL', 'TECNICO ADMINISTRATIVO PLENO',     'TEC-ADM-PL', 0],
            ['DIR-ESC',    'DIRETOR ESCOLAR',                  'DIR-ESC',    0],
            ['VDIR-ESC',   'VICE-DIRETOR ESCOLAR',             'VDIR-ESC',   0],
            ['DAS-3',      'COORDENADORA PEDAGOGICA (DAS-3)',  'DAS-3',      4500.00],
            ['DAS-2',      'ASSESSOR TECNICO (DAS-2)',         'DAS-2',      3200.00],
        ];

        // Determina coluna de salário (schema-defensive)
        $temSalario = Schema::hasColumn('CARGO', 'CARGO_SALARIO');
        $temSalarioBase = Schema::hasColumn('CARGO', 'CARGO_SALARIO_BASE');

        $map = [];
        foreach ($cargos as [$cod, $nome, $sigla, $salDefault]) {
            $nomeCompleto = self::NOME_PREFIX . ' CARGO ' . $cod;  // pra não colidir com cargos reais do PMSL

            $existente = DB::table('CARGO')->where('CARGO_NOME', $nomeCompleto)->value('CARGO_ID');

            $data = [
                'CARGO_NOME'  => $nomeCompleto,
                'CARGO_SIGLA' => $sigla,
                'CARGO_ATIVO' => 1,
            ];
            if ($temSalario) {
                $data['CARGO_SALARIO'] = $salDefault;
            } elseif ($temSalarioBase) {
                $data['CARGO_SALARIO_BASE'] = $salDefault;
            }

            if ($existente) {
                DB::table('CARGO')->where('CARGO_ID', $existente)->update($data);
                $map[$cod] = (int) $existente;
            } else {
                $map[$cod] = (int) DB::table('CARGO')->insertGetId($data);
            }
        }

        return $map;
    }

    /**
     * Popula FUNCAO com 2 funções descritivas (Diretor/Vice).
     *
     * NOTA TÉCNICA: no schema atual do GENTE v3, FUNCAO existe como tabela mas
     * NÃO tem FK reverso de FUNCIONARIO. Logo, este registro é puramente descritivo —
     * a "função" do servidor é expressa via VINCULO_TIPO=funcao_confianca + CARGO + ADICIONAL_SERVIDOR.
     * Quando a integração FUNCIONARIO↔FUNCAO for adicionada, basta resolver o FUNCAO_ID por NOME.
     */
    private function popularFuncoes(): void
    {
        if (! Schema::hasTable('FUNCAO')) {
            return;
        }

        $funcoes = [
            ['Diretor Escolar',      'DIR-ESC'],
            ['Vice-Diretor Escolar', 'VDIR-ESC'],
        ];

        foreach ($funcoes as [$nome, $sigla]) {
            $data = [
                'FUNCAO_NOME'  => $nome,
                'FUNCAO_SIGLA' => $sigla,
            ];
            // FUNCAO_ATIVA é do model; tabela pode ter FUNCAO_ATIVO (typo) — schema-defensive
            if (Schema::hasColumn('FUNCAO', 'FUNCAO_ATIVA')) {
                $data['FUNCAO_ATIVA'] = 1;
            } elseif (Schema::hasColumn('FUNCAO', 'FUNCAO_ATIVO')) {
                $data['FUNCAO_ATIVO'] = 1;
            }

            DB::table('FUNCAO')->updateOrInsert(['FUNCAO_NOME' => $nome], $data);
        }
    }

    private function resolverColDescTabelaGenerica(): ?string
    {
        if (Schema::hasColumn('TABELA_GENERICA', 'COLUNA_DESCRICAO')) {
            return 'COLUNA_DESCRICAO';
        }
        if (Schema::hasColumn('TABELA_GENERICA', 'DESCRICAO')) {
            return 'DESCRICAO';
        }
        return null;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Definição dos 12 servidores demo
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Cada servidor tem:
     *   - codigo: identificador interno (DEMO-01..DEMO-12)
     *   - nome, cpf_sufixo (4 dígitos finais), data_nascimento, sexo
     *   - vinculo_codigo: 'efetivo' | 'comissao_puro' | 'funcao_confianca'
     *   - regime_prev: 'RPPS' | 'RGPS'
     *   - carreira_codigo: 'GERAL' | 'MAGISTERIO' | null (sem carreira → cai no fallback CARGO)
     *   - classe, referencia: para lookup TABELA_SALARIAL (se carreira presente)
     *   - data_admissao
     *   - dependentes_irrf
     *   - apuracao_jan: ['horas_falta' => float]   (ou null = sem apuração)
     *   - apuracao_fev: ['horas_falta' => float]
     *   - afastamento_jan: ['tipo_nome' => string, 'inicio' => 'YYYY-MM-DD', 'fim' => 'YYYY-MM-DD'] | null
     *   - afastamento_fev: idem
     */
    private function definirServidores(): array
    {
        return [
            // Cenário 1: Sem falta em ambos os meses (controle) — PNS1-A R$ 2.118,00
            [
                'codigo' => 'DEMO-01', 'nome' => 'ANA PROFESSORA SEM FALTA',
                'cpf_sufixo' => '0001', 'data_nascimento' => '1985-03-12', 'sexo' => 1,
                'vinculo_codigo' => 'efetivo', 'regime_prev' => 'RPPS',
                'carreira_codigo' => 'MAGISTERIO', 'classe' => 'PNS1', 'referencia' => 'A',
                'cargo_codigo' => 'PROF-EB', 'atribuicao_codigo' => 'DEMO-PROF',
                'adicional' => ['rubrica_codigo' => '025', 'tipo' => 'fixo', 'valor' => 200.00, 'obs' => 'Gratificação de Regência de Classe'],
                'data_admissao' => '2018-03-01', 'dependentes_irrf' => 0,
                'apuracao_jan' => ['horas_falta' => 0.0],
                'apuracao_fev' => ['horas_falta' => 0.0],
                'afastamento_jan' => null, 'afastamento_fev' => null,
            ],
            // Cenário 2: 2 faltas injustificadas em Jan, sem nada em Fev — PNS1-B R$ 2.170,95
            [
                'codigo' => 'DEMO-02', 'nome' => 'BRUNO PROFESSOR DUAS FALTAS',
                'cpf_sufixo' => '0002', 'data_nascimento' => '1988-07-20', 'sexo' => 2,
                'vinculo_codigo' => 'efetivo', 'regime_prev' => 'RPPS',
                'carreira_codigo' => 'MAGISTERIO', 'classe' => 'PNS1', 'referencia' => 'B',
                'cargo_codigo' => 'PROF-EB', 'atribuicao_codigo' => 'DEMO-PROF',
                'adicional' => ['rubrica_codigo' => '025', 'tipo' => 'fixo', 'valor' => 200.00, 'obs' => 'Gratificação de Regência de Classe'],
                'data_admissao' => '2017-01-15', 'dependentes_irrf' => 1,
                'apuracao_jan' => ['horas_falta' => 16.0],  // 2 dias × 8h
                'apuracao_fev' => ['horas_falta' => 0.0],
                'afastamento_jan' => null, 'afastamento_fev' => null,
            ],
            // Cenário 3: 3 dias atestado em Fev (LICENCA_MEDICA → abonado) — PNS1-C R$ 2.225,22
            [
                'codigo' => 'DEMO-03', 'nome' => 'CARLA PROFESSORA ATESTADO',
                'cpf_sufixo' => '0003', 'data_nascimento' => '1990-11-05', 'sexo' => 1,
                'vinculo_codigo' => 'efetivo', 'regime_prev' => 'RPPS',
                'carreira_codigo' => 'MAGISTERIO', 'classe' => 'PNS1', 'referencia' => 'C',
                'cargo_codigo' => 'PROF-EB', 'atribuicao_codigo' => 'DEMO-PROF',
                'adicional' => ['rubrica_codigo' => '025', 'tipo' => 'fixo', 'valor' => 200.00, 'obs' => 'Gratificação de Regência de Classe'],
                'data_admissao' => '2019-08-10', 'dependentes_irrf' => 2,
                'apuracao_jan' => ['horas_falta' => 0.0],
                'apuracao_fev' => ['horas_falta' => 0.0],  // atestado já abonado, não vira falta
                'afastamento_jan' => null,
                'afastamento_fev' => ['tipo_nome' => 'LICENCA_MEDICA', 'inicio' => '2026-02-10', 'fim' => '2026-02-12'],
            ],
            // Cenário 4: Complexo — 1 falta + 1 atestado em Jan, 5 faltas em Fev — PNS1-D R$ 2.280,85
            [
                'codigo' => 'DEMO-04', 'nome' => 'DANIEL PROFESSOR COMPLEXO',
                'cpf_sufixo' => '0004', 'data_nascimento' => '1982-02-28', 'sexo' => 2,
                'vinculo_codigo' => 'efetivo', 'regime_prev' => 'RPPS',
                'carreira_codigo' => 'MAGISTERIO', 'classe' => 'PNS1', 'referencia' => 'D',
                'cargo_codigo' => 'PROF-EB', 'atribuicao_codigo' => 'DEMO-PROF',
                'adicional' => ['rubrica_codigo' => '025', 'tipo' => 'fixo', 'valor' => 200.00, 'obs' => 'Gratificação de Regência de Classe'],
                'data_admissao' => '2015-06-01', 'dependentes_irrf' => 0,
                'apuracao_jan' => ['horas_falta' => 8.0],   // 1 dia falta (atestado já abonado)
                'apuracao_fev' => ['horas_falta' => 40.0],  // 5 dias falta
                'afastamento_jan' => ['tipo_nome' => 'LICENCA_MEDICA', 'inicio' => '2026-01-15', 'fim' => '2026-01-15'],
                'afastamento_fev' => null,
            ],
            // Cenário 5: Técnico Geral I-A, sem falta (controle Geral RPPS) — R$ 782,42
            [
                'codigo' => 'DEMO-05', 'nome' => 'EDUARDO TECNICO I-A',
                'cpf_sufixo' => '0005', 'data_nascimento' => '1992-09-18', 'sexo' => 2,
                'vinculo_codigo' => 'efetivo', 'regime_prev' => 'RPPS',
                'carreira_codigo' => 'GERAL', 'classe' => 'I', 'referencia' => 'A',
                'cargo_codigo' => 'TEC-ADM', 'atribuicao_codigo' => 'DEMO-TEC',
                'adicional' => null,
                'data_admissao' => '2020-02-01', 'dependentes_irrf' => 0,
                'apuracao_jan' => ['horas_falta' => 0.0],
                'apuracao_fev' => ['horas_falta' => 0.0],
                'afastamento_jan' => null, 'afastamento_fev' => null,
            ],
            // Cenário 6: Técnico Geral II-A, faltas em ambos os meses (acúmulo) — R$ 863,61
            [
                'codigo' => 'DEMO-06', 'nome' => 'FERNANDA TECNICA FALTAS',
                'cpf_sufixo' => '0006', 'data_nascimento' => '1987-04-22', 'sexo' => 1,
                'vinculo_codigo' => 'efetivo', 'regime_prev' => 'RPPS',
                'carreira_codigo' => 'GERAL', 'classe' => 'II', 'referencia' => 'A',
                'cargo_codigo' => 'TEC-ADM', 'atribuicao_codigo' => 'DEMO-TEC',
                'adicional' => null,
                'data_admissao' => '2016-11-15', 'dependentes_irrf' => 1,
                'apuracao_jan' => ['horas_falta' => 24.0],  // 3 dias
                'apuracao_fev' => ['horas_falta' => 16.0],  // 2 dias
                'afastamento_jan' => null, 'afastamento_fev' => null,
            ],
            // Cenário 7: Geral I-B, licença maternidade longa em ambos — R$ 801,95
            [
                'codigo' => 'DEMO-07', 'nome' => 'GABRIELA TECNICA MATERNIDADE',
                'cpf_sufixo' => '0007', 'data_nascimento' => '1989-12-03', 'sexo' => 1,
                'vinculo_codigo' => 'efetivo', 'regime_prev' => 'RPPS',
                'carreira_codigo' => 'GERAL', 'classe' => 'I', 'referencia' => 'B',
                'cargo_codigo' => 'TEC-ADM-PL', 'atribuicao_codigo' => 'DEMO-TEC',
                'adicional' => null,
                'data_admissao' => '2014-04-20', 'dependentes_irrf' => 1,
                'apuracao_jan' => ['horas_falta' => 0.0],  // remunerada via afastamento
                'apuracao_fev' => ['horas_falta' => 0.0],
                'afastamento_jan' => ['tipo_nome' => 'LICENCA_MATERNIDADE', 'inicio' => '2026-01-01', 'fim' => '2026-01-31'],
                'afastamento_fev' => ['tipo_nome' => 'LICENCA_MATERNIDADE', 'inicio' => '2026-02-01', 'fim' => '2026-02-28'],
            ],
            // Cenário 8: Geral I-C, sem falta (controle escalão maior) — R$ 822,03
            [
                'codigo' => 'DEMO-08', 'nome' => 'HUGO TECNICO I-C',
                'cpf_sufixo' => '0008', 'data_nascimento' => '1980-06-30', 'sexo' => 2,
                'vinculo_codigo' => 'efetivo', 'regime_prev' => 'RPPS',
                'carreira_codigo' => 'GERAL', 'classe' => 'I', 'referencia' => 'C',
                'cargo_codigo' => 'TEC-ADM', 'atribuicao_codigo' => 'DEMO-TEC',
                'adicional' => null,
                'data_admissao' => '2010-03-10', 'dependentes_irrf' => 2,
                'apuracao_jan' => ['horas_falta' => 0.0],
                'apuracao_fev' => ['horas_falta' => 0.0],
                'afastamento_jan' => null, 'afastamento_fev' => null,
            ],
            // Cenário 9: Coordenadora CC Pura RGPS — DAS-3 R$ 4.500 (fallback CARGO_SALARIO)
            [
                'codigo' => 'DEMO-09', 'nome' => 'ISABELA COORDENADORA CC RGPS',
                'cpf_sufixo' => '0009', 'data_nascimento' => '1986-08-14', 'sexo' => 1,
                'vinculo_codigo' => 'comissao_puro', 'regime_prev' => 'RGPS',
                'carreira_codigo' => null, 'classe' => null, 'referencia' => null,
                'cargo_codigo' => 'DAS-3', 'atribuicao_codigo' => 'DEMO-COORD',
                'adicional' => null,
                'data_admissao' => '2024-01-15', 'dependentes_irrf' => 0,
                'apuracao_jan' => ['horas_falta' => 0.0],
                'apuracao_fev' => ['horas_falta' => 0.0],
                'afastamento_jan' => null, 'afastamento_fev' => null,
                'cargo_salario_demo' => 4500.00,  // valor para CARGO_SALARIO de fallback
            ],
            // Cenário 10: Assessor CC RGPS com 1 falta em Jan — DAS-2 R$ 3.200 (testar desconto RGPS)
            [
                'codigo' => 'DEMO-10', 'nome' => 'JOAO ASSESSOR CC RGPS FALTA',
                'cpf_sufixo' => '0010', 'data_nascimento' => '1991-10-25', 'sexo' => 2,
                'vinculo_codigo' => 'comissao_puro', 'regime_prev' => 'RGPS',
                'carreira_codigo' => null, 'classe' => null, 'referencia' => null,
                'cargo_codigo' => 'DAS-2', 'atribuicao_codigo' => 'DEMO-ASSESS',
                'adicional' => null,
                'data_admissao' => '2024-03-01', 'dependentes_irrf' => 1,
                'apuracao_jan' => ['horas_falta' => 8.0],  // 1 dia
                'apuracao_fev' => ['horas_falta' => 0.0],
                'afastamento_jan' => null, 'afastamento_fev' => null,
                'cargo_salario_demo' => 3200.00,
            ],
            // Cenário 11: Diretora Escolar FC — Geral I-D R$ 842,56 + Gratificação Direção R$ 1.500
            [
                'codigo' => 'DEMO-11', 'nome' => 'KARINA DIRETORA FC',
                'cpf_sufixo' => '0011', 'data_nascimento' => '1978-11-08', 'sexo' => 1,
                'vinculo_codigo' => 'funcao_confianca', 'regime_prev' => 'RPPS',
                'carreira_codigo' => 'GERAL', 'classe' => 'I', 'referencia' => 'D',
                'cargo_codigo' => 'DIR-ESC', 'atribuicao_codigo' => 'DEMO-DIR',
                'adicional' => ['rubrica_codigo' => '010', 'tipo' => 'fixo', 'valor' => 1500.00, 'obs' => 'Gratificação de Direção Escolar'],
                'data_admissao' => '2008-04-15', 'dependentes_irrf' => 2,
                'apuracao_jan' => ['horas_falta' => 0.0],
                'apuracao_fev' => ['horas_falta' => 0.0],  // atestado abonado, não vira falta
                'afastamento_jan' => null,
                'afastamento_fev' => ['tipo_nome' => 'LICENCA_MEDICA', 'inicio' => '2026-02-20', 'fim' => '2026-02-21'],
            ],
            // Cenário 12: Vice-Diretor FC + paternidade Jan — Geral III-C R$ 1.001,57 + Grat. Vice R$ 800
            [
                'codigo' => 'DEMO-12', 'nome' => 'LUCAS VICEDIRETOR FC PATERNIDADE',
                'cpf_sufixo' => '0012', 'data_nascimento' => '1983-05-17', 'sexo' => 2,
                'vinculo_codigo' => 'funcao_confianca', 'regime_prev' => 'RPPS',
                'carreira_codigo' => 'GERAL', 'classe' => 'III', 'referencia' => 'C',
                'cargo_codigo' => 'VDIR-ESC', 'atribuicao_codigo' => 'DEMO-VDIR',
                'adicional' => ['rubrica_codigo' => '010', 'tipo' => 'fixo', 'valor' => 800.00, 'obs' => 'Gratificação de Vice-Direção Escolar'],
                'data_admissao' => '2012-09-01', 'dependentes_irrf' => 3,
                'apuracao_jan' => ['horas_falta' => 0.0],  // paternidade abonada
                'apuracao_fev' => ['horas_falta' => 0.0],
                'afastamento_jan' => ['tipo_nome' => 'LICENCA_PATERNIDADE', 'inicio' => '2026-01-08', 'fim' => '2026-01-12'],
                'afastamento_fev' => null,
            ],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Inserção: PESSOA, USUARIO, FUNCIONARIO, LOTACAO, ATRIBUICAO_LOTACAO
    // ═══════════════════════════════════════════════════════════════════════

    private function gerarCpfSintetico(string $sufixo): string
    {
        // Padrão: 999000XXXX (10 dígitos = 11 com último arbitrário)
        // Não calcula DV verdadeiro — é só um identificador único
        return self::CPF_PREFIX . str_pad($sufixo, 6, '0', STR_PAD_LEFT);
    }

    private function upsertPessoa(array $s): int
    {
        $cpf = $this->gerarCpfSintetico($s['cpf_sufixo']);
        $nome = self::NOME_PREFIX . ' ' . $s['nome'];

        $existente = DB::table('PESSOA')->where('PESSOA_NOME', $nome)->value('PESSOA_ID');

        $data = [
            'PESSOA_NOME' => $nome,
            'PESSOA_CPF_NUMERO' => $cpf,
            'PESSOA_DATA_NASCIMENTO' => $s['data_nascimento'],
            'PESSOA_SEXO' => $s['sexo'],
            'PESSOA_STATUS' => 1,
        ];

        if (Schema::hasColumn('PESSOA', 'PESSOA_DEPENDENTES_IRRF')) {
            $data['PESSOA_DEPENDENTES_IRRF'] = (int) $s['dependentes_irrf'];
        }
        if (Schema::hasColumn('PESSOA', 'PESSOA_DATA_CADASTRO')) {
            $data['PESSOA_DATA_CADASTRO'] = now();
        }

        if ($existente) {
            DB::table('PESSOA')->where('PESSOA_ID', $existente)->update($data);
            return (int) $existente;
        }

        return (int) DB::table('PESSOA')->insertGetId($data);
    }

    private function upsertUsuario(array $s, int $pessoaId): int
    {
        $cpf = $this->gerarCpfSintetico($s['cpf_sufixo']);
        $nome = self::NOME_PREFIX . ' ' . $s['nome'];

        $existente = DB::table('USUARIO')->where('USUARIO_LOGIN', $cpf)->value('USUARIO_ID');

        $data = [
            'USUARIO_LOGIN' => $cpf,
            'USUARIO_NOME' => $nome,
            'USUARIO_ATIVO' => 1,
        ];

        if (Schema::hasColumn('USUARIO', 'USUARIO_CPF')) {
            $data['USUARIO_CPF'] = $cpf;
        } elseif (Schema::hasColumn('USUARIO', 'CPF')) {
            $data['CPF'] = $cpf;
        }

        if (! $existente) {
            $data['USUARIO_SENHA'] = Hash::make(Str::random(16));
        }

        if ($existente) {
            DB::table('USUARIO')->where('USUARIO_ID', $existente)->update($data);
            $usuarioId = (int) $existente;
        } else {
            $usuarioId = (int) DB::table('USUARIO')->insertGetId($data);
        }

        // Vincular USUARIO_ID na PESSOA (se a coluna existir)
        if (Schema::hasColumn('PESSOA', 'USUARIO_ID')) {
            DB::table('PESSOA')->where('PESSOA_ID', $pessoaId)->update(['USUARIO_ID' => $usuarioId]);
        }

        return $usuarioId;
    }

    private function upsertFuncionario(array $s, int $pessoaId, int $usuarioId, array $ctx): int
    {
        $matricula = 'DEMOPOC' . $s['cpf_sufixo'];
        $existente = DB::table('FUNCIONARIO')->where('FUNCIONARIO_MATRICULA', $matricula)->value('FUNCIONARIO_ID');

        $vinculoId = match ($s['vinculo_codigo']) {
            'comissao_puro'    => $ctx['vinc_comissao'],
            'funcao_confianca' => $ctx['vinc_fc'],
            default            => $ctx['vinc_efetivo'],
        };

        $carreiraId = null;
        if ($s['carreira_codigo'] === 'GERAL') {
            $carreiraId = $ctx['carr_geral'];
        } elseif ($s['carreira_codigo'] === 'MAGISTERIO') {
            $carreiraId = $ctx['carr_magisterio'];
        }

        // Resolve CARGO_ID a partir do código (mapa preparado em prepararContexto)
        $cargoId = null;
        if (! empty($s['cargo_codigo']) && isset($ctx['cargos_map'][$s['cargo_codigo']])) {
            $cargoId = $ctx['cargos_map'][$s['cargo_codigo']];
        }

        $data = [
            'PESSOA_ID' => $pessoaId,
            'FUNCIONARIO_MATRICULA' => $matricula,
            'FUNCIONARIO_DATA_INICIO' => $s['data_admissao'],
            'USUARIO_ID' => $usuarioId,
        ];

        // Schema-defensive — só preenche colunas que existem
        if (Schema::hasColumn('FUNCIONARIO', 'VINCULO_ID')) {
            $data['VINCULO_ID'] = $vinculoId;
        }
        if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_REGIME_PREV')) {
            $data['FUNCIONARIO_REGIME_PREV'] = $s['regime_prev'];
        }
        if ($carreiraId && Schema::hasColumn('FUNCIONARIO', 'CARREIRA_ID')) {
            $data['CARREIRA_ID'] = $carreiraId;
        }
        if ($cargoId && Schema::hasColumn('FUNCIONARIO', 'CARGO_ID')) {
            $data['CARGO_ID'] = $cargoId;
        }
        if ($s['classe'] && Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_CLASSE')) {
            $data['FUNCIONARIO_CLASSE'] = $s['classe'];
        }
        if ($s['referencia'] && Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_REFERENCIA')) {
            $data['FUNCIONARIO_REFERENCIA'] = $s['referencia'];
        }
        if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_ATIVO')) {
            $data['FUNCIONARIO_ATIVO'] = 1;
        }
        if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_CADASTRO')) {
            $data['FUNCIONARIO_DATA_CADASTRO'] = now();
        }

        if ($existente) {
            DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $existente)->update($data);
            $funcId = (int) $existente;
        } else {
            $funcId = (int) DB::table('FUNCIONARIO')->insertGetId($data);
        }

        return $funcId;
    }

    /**
     * @deprecated Mantido para referência histórica. Cargos agora são criados em
     *             {@see upsertCargosDemo} chamado pelo {@see prepararContexto}.
     */
    private function vincularCargoComSalarioDemo(int $funcId, array $s): void
    {
        // intencionalmente vazio — substituído pela criação prévia de CARGOs no contexto
    }

    private function upsertLotacao(int $funcId, array $s, array $ctx): int
    {
        $vinculoId = match ($s['vinculo_codigo']) {
            'comissao_puro'    => $ctx['vinc_comissao'],
            'funcao_confianca' => $ctx['vinc_fc'],
            default            => $ctx['vinc_efetivo'],
        };

        $existente = DB::table('LOTACAO')
            ->where('FUNCIONARIO_ID', $funcId)
            ->where('SETOR_ID', $ctx['setor_id'])
            ->value('LOTACAO_ID');

        $data = [
            'FUNCIONARIO_ID' => $funcId,
            'VINCULO_ID' => $vinculoId,
            'SETOR_ID' => $ctx['setor_id'],
            'LOTACAO_DATA_INICIO' => $s['data_admissao'],
            'LOTACAO_DATA_FIM' => null,
        ];

        if ($existente) {
            DB::table('LOTACAO')->where('LOTACAO_ID', $existente)->update($data);
            return (int) $existente;
        }

        return (int) DB::table('LOTACAO')->insertGetId($data);
    }

    private function upsertAtribuicaoLotacao(int $lotacaoId, array $s, array $ctx): void
    {
        // Resolve a atribuição específica do servidor pelo código
        $atribuicaoCodigo = $s['atribuicao_codigo'] ?? 'DEMO-TEC';  // fallback genérico
        $atribuicaoId = $ctx['atribuicoes_map'][$atribuicaoCodigo] ?? null;

        if (! $atribuicaoId) {
            // Fallback: pega a primeira atribuição do mapa
            $atribuicaoId = reset($ctx['atribuicoes_map']);
            if (! $atribuicaoId) {
                return; // não tem nenhuma atribuição disponível
            }
        }

        $existe = DB::table('ATRIBUICAO_LOTACAO')
            ->where('LOTACAO_ID', $lotacaoId)
            ->where('ATRIBUICAO_ID', $atribuicaoId)
            ->exists();

        if ($existe) {
            return;
        }

        $data = [
            'LOTACAO_ID' => $lotacaoId,
            'ATRIBUICAO_ID' => $atribuicaoId,
            'ATRIBUICAO_LOTACAO_INICIO' => Carbon::now()->subYears(2)->toDateString(),
            'ATRIBUICAO_LOTACAO_CARGA_HORARIA' => 40,
        ];

        DB::table('ATRIBUICAO_LOTACAO')->insert($data);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // APURACAO_PONTO — Janeiro/2026 e Fevereiro/2026
    // ═══════════════════════════════════════════════════════════════════════

    private function popularApuracoes(array $funcMap, array $servidores, array $ctx): void
    {
        $jornadaMensal = $this->jornadaMensalDias() * self::HORAS_DIA;

        foreach ($servidores as $s) {
            $funcId = $funcMap[$s['codigo']] ?? null;
            if (! $funcId) continue;

            // Janeiro/2026 — 31 dias
            if ($s['apuracao_jan'] !== null) {
                $this->upsertApuracao($funcId, self::COMP_APURACAO_1, $s['apuracao_jan']['horas_falta'], 31);
            }

            // Fevereiro/2026 — 28 dias
            if ($s['apuracao_fev'] !== null) {
                $this->upsertApuracao($funcId, self::COMP_APURACAO_2, $s['apuracao_fev']['horas_falta'], 28);
            }
        }

        $this->command->info('Apurações Jan/Fev populadas (status=FECHADA)');
    }

    private function upsertApuracao(int $funcId, string $competencia, float $horasFalta, int $diasMes): void
    {
        // Premissa: jornada média 8h/dia × dias úteis (~22) = 176h trabalhadas no mês
        // Para simplificar, calculamos:  trab = (diasMes * 8) - falta - extra
        $diasUteisAprox = (int) round($diasMes * 22 / 30);
        $horasEsperadas = $diasUteisAprox * self::HORAS_DIA;
        $horasTrab = max(0, $horasEsperadas - $horasFalta);

        $data = [
            'FUNCIONARIO_ID' => $funcId,
            'APURACAO_COMPETENCIA' => $competencia,
            'APURACAO_HORAS_TRAB' => $horasTrab,
            'APURACAO_HORAS_EXTRA' => 0.0,
            'APURACAO_HORAS_FALTA' => $horasFalta,
            'APURACAO_STATUS' => 'FECHADA',
            'APURACAO_FECHADA_EM' => now(),
        ];

        DB::table('APURACAO_PONTO')->updateOrInsert(
            ['FUNCIONARIO_ID' => $funcId, 'APURACAO_COMPETENCIA' => $competencia],
            $data
        );
    }

    private function jornadaMensalDias(): int
    {
        return 22; // dias úteis aproximados (placeholder)
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AFASTAMENTOS
    // ═══════════════════════════════════════════════════════════════════════

    private function popularAfastamentos(array $funcMap, array $servidores, array $ctx): void
    {
        // Resolve IDs dos tipos de afastamento via TABELA_GENERICA
        $colDesc = $this->resolverColDescTabelaGenerica();
        if (! $colDesc) {
            $this->command->warn('TABELA_GENERICA sem coluna descritiva — afastamentos não populados.');
            return;
        }

        $tiposDesejados = ['LICENCA_MEDICA', 'LICENCA_MATERNIDADE', 'LICENCA_PATERNIDADE'];
        $mapaTipos = DB::table('TABELA_GENERICA')
            ->where('TABELA_ID', 5)  // RTG::TIPO_AFASTAMENTO
            ->whereIn($colDesc, $tiposDesejados)
            ->pluck('COLUNA_ID', $colDesc)
            ->all();

        // Se faltar alguns tipos, criar (idempotente)
        foreach ($tiposDesejados as $tipoNome) {
            if (! isset($mapaTipos[$tipoNome])) {
                $maxId = (int) DB::table('TABELA_GENERICA')
                    ->where('TABELA_ID', 5)
                    ->max('COLUNA_ID');
                $novoId = $maxId + 1;

                $insert = [
                    'TABELA_ID' => 5,
                    'COLUNA_ID' => $novoId,
                    $colDesc => $tipoNome,
                ];
                if (Schema::hasColumn('TABELA_GENERICA', 'ATIVO')) {
                    $insert['ATIVO'] = 1;
                }
                DB::table('TABELA_GENERICA')->insert($insert);
                $mapaTipos[$tipoNome] = $novoId;
                $this->command->info("Tipo de afastamento '{$tipoNome}' criado em TABELA_GENERICA com COLUNA_ID={$novoId}");
            }
        }

        $count = 0;
        foreach ($servidores as $s) {
            $funcId = $funcMap[$s['codigo']] ?? null;
            if (! $funcId) continue;

            foreach (['afastamento_jan', 'afastamento_fev'] as $key) {
                if (! $s[$key]) continue;
                $a = $s[$key];
                $tipoId = $mapaTipos[$a['tipo_nome']] ?? null;
                if (! $tipoId) continue;

                // Idempotente: deleta+insere por (FUNCIONARIO_ID, AFASTAMENTO_DATA_INICIO)
                DB::table('AFASTAMENTO')
                    ->where('FUNCIONARIO_ID', $funcId)
                    ->where('AFASTAMENTO_DATA_INICIO', $a['inicio'])
                    ->delete();

                DB::table('AFASTAMENTO')->insert([
                    'FUNCIONARIO_ID' => $funcId,
                    'AFASTAMENTO_DATA_INICIO' => $a['inicio'],
                    'AFASTAMENTO_DATA_FIM' => $a['fim'],
                    'AFASTAMENTO_TIPO' => (int) $tipoId,
                ]);
                $count++;
            }
        }

        $this->command->info("Afastamentos populados: {$count}");
    }

    // ══════════════════════════════════════════════════════════════════════
    // ADICIONAL_SERVIDOR — Camada 2 (adicionais permanentes)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Cria registros em ADICIONAL_SERVIDOR para servidores com 'adicional' definido.
     *
     * Idempotente: deleta+insere por (FUNCIONARIO_ID, RUBRICA_ID) com OBS prefixada DEMO.
     * Isso evita conflito com adicionais reais e permite re-execução do seeder.
     */
    private function popularAdicionais(array $funcMap, array $servidores, array $ctx): void
    {
        if (! Schema::hasTable('ADICIONAL_SERVIDOR')) {
            $this->command->warn('Tabela ADICIONAL_SERVIDOR ausente — adicionais não populados.');
            return;
        }

        // Pré-resolve RUBRICA_IDs de todas as rubricas usadas nos cenários
        $codigosRubrica = collect($servidores)
            ->pluck('adicional.rubrica_codigo')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($codigosRubrica)) {
            $this->command->info('Nenhum adicional definido nos cenários — nada a fazer.');
            return;
        }

        $rubricasMap = DB::table('RUBRICA')
            ->whereIn('RUBRICA_CODIGO', $codigosRubrica)
            ->pluck('RUBRICA_ID', 'RUBRICA_CODIGO')
            ->all();

        // Verifica se todas foram encontradas
        foreach ($codigosRubrica as $cod) {
            if (! isset($rubricasMap[$cod])) {
                $this->command->warn("Rubrica RUBRICA_CODIGO={$cod} não encontrada — servidores que dependem dela ficam sem adicional. Rode RubricasCatalogoSeeder antes.");
            }
        }

        $count = 0;
        foreach ($servidores as $s) {
            if (empty($s['adicional'])) continue;

            $funcId = $funcMap[$s['codigo']] ?? null;
            if (! $funcId) continue;

            $a = $s['adicional'];
            $rubricaCod = $a['rubrica_codigo'] ?? null;
            $rubricaId = $rubricaCod ? ($rubricasMap[$rubricaCod] ?? null) : null;
            if (! $rubricaId) continue;

            $obs = '[DEMO POC] ' . ($a['obs'] ?? 'Adicional permanente demo');

            // Idempotente: remove qualquer adicional anterior do par (func, rubrica) marcado como DEMO
            DB::table('ADICIONAL_SERVIDOR')
                ->where('FUNCIONARIO_ID', $funcId)
                ->where('RUBRICA_ID', $rubricaId)
                ->where('ADICIONAL_OBS', 'like', '[DEMO POC]%')
                ->delete();

            $data = [
                'FUNCIONARIO_ID'         => $funcId,
                'RUBRICA_ID'             => (int) $rubricaId,
                'ADICIONAL_TIPO'         => $a['tipo'] ?? 'fixo',
                'ADICIONAL_VALOR'        => (float) ($a['valor'] ?? 0),
                'ADICIONAL_INCIDE_PREV'  => $a['incide_prev'] ?? true,
                'ADICIONAL_INCIDE_IRRF'  => $a['incide_irrf'] ?? true,
                'ADICIONAL_INCIDE_FGTS'  => $a['incide_fgts'] ?? false,
                'ADICIONAL_VIGENCIA_INICIO' => $s['data_admissao'],
                'ADICIONAL_VIGENCIA_FIM' => null,  // permanente
                'ADICIONAL_OBS'          => $obs,
                'created_at'             => now(),
                'updated_at'             => now(),
            ];

            // Schema-defensive em colunas opcionais
            if (Schema::hasColumn('ADICIONAL_SERVIDOR', 'ADICIONAL_BASE')) {
                $data['ADICIONAL_BASE'] = $a['base'] ?? null;
            }
            if (Schema::hasColumn('ADICIONAL_SERVIDOR', 'ADICIONAL_ATO_ADM')) {
                $data['ADICIONAL_ATO_ADM'] = $a['ato_adm'] ?? null;
            }

            DB::table('ADICIONAL_SERVIDOR')->insert($data);
            $count++;
        }

        $this->command->info("Adicionais populados: {$count} (rubricas: " . implode(',', $codigosRubrica) . ')');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FOLHAS — Fev/2026 e Mar/2026
    // ═══════════════════════════════════════════════════════════════════════

    private function criarFolhas(array $ctx): void
    {
        $vinculoId = $ctx['vinc_efetivo']; // Folha "default" do vínculo principal

        $folha1 = $this->upsertFolha(
            'DEMO POC — Folha Fev/2026',
            self::COMP_FOLHA_1,
            $vinculoId
        );
        $folha2 = $this->upsertFolha(
            'DEMO POC — Folha Mar/2026',
            self::COMP_FOLHA_2,
            $vinculoId
        );

        $this->command->info("Folhas criadas: FOLHA_ID={$folha1} (Fev/2026), FOLHA_ID={$folha2} (Mar/2026)");
    }

    private function upsertFolha(string $descricao, string $competencia, int $vinculoId): int
    {
        // FOLHA_TIPO=1 (mensal) — assumindo que o seeder de TABELA_GENERICA já populou
        $folhaTipoId = $this->resolverFolhaTipoMensal();

        $data = [
            'FOLHA_DESCRICAO' => $descricao,
            'FOLHA_TIPO' => $folhaTipoId,
            'VINCULO_ID' => $vinculoId,
            'FOLHA_COMPETENCIA' => $competencia,
            'FOLHA_QTD_SERVIDORES' => 0,
            'FOLHA_VALOR_TOTAL' => 0,
        ];

        $existente = DB::table('FOLHA')->where('FOLHA_DESCRICAO', $descricao)->value('FOLHA_ID');

        if ($existente) {
            DB::table('FOLHA')->where('FOLHA_ID', $existente)->update($data);
            return (int) $existente;
        }

        return (int) DB::table('FOLHA')->insertGetId($data);
    }

    private function resolverFolhaTipoMensal(): int
    {
        $colDesc = $this->resolverColDescTabelaGenerica();
        if ($colDesc) {
            $id = DB::table('TABELA_GENERICA')
                ->where('TABELA_ID', 32)  // RTG::TIPOS_FOLHA
                ->where(function ($q) use ($colDesc) {
                    $q->where($colDesc, 'like', '%MENSAL%')
                      ->orWhere($colDesc, 'like', '%NORMAL%')
                      ->orWhere($colDesc, 'like', '%FOLHA%');
                })
                ->where('COLUNA_ID', '!=', 0)
                ->orderBy('COLUNA_ID')
                ->value('COLUNA_ID');
            if ($id) {
                return (int) $id;
            }
        }
        // Fallback: usar 1
        return 1;
    }
}
