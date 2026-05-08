<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * SECRETARIAS-SEED — massa unificada São Luís (PMSLz) + cobertura das abas do sidebar.
 *
 * Orquestra, na ordem correcta, os seeders que antes estavam espalhados no {@see DatabaseSeeder}:
 * enums, RBAC, PCCV, organograma (26 secretarias + setores), cargos/salários, funcionários de teste,
 * utilizadores PMSLz, PCASP/SAGRES, cobertura de sidebar (fase 1 e 2), feriados e abas de configuração.
 *
 * **Fora deste ficheiro:** {@see DaviSupremoSeeder} (perfil fundador) continua a ser invocado pelo `DatabaseSeeder`
 * depois deste, para poder exigir SEMED/SETOR já materializados.
 *
 * **Saúde e benefícios (10B, opt-in):** com `GENTE_SAUDE_BENEFICIOS_SEED=1`, após `FuncionariosPMSLzSeeder` corre-se
 * {@see SaudeEBeneficiosCoverageSeeder} (LM &gt;15d, CID Z73, consignação ≤30% vencimento-base, `AGENDAMENTO_EXAME`).
 *
 * **Volume massivo (stress):** com `GENTE_STRESS_SEED=1` (ex.: Docker de homolog), no final corre-se
 * {@see SuperSeederEstresseMigracao} (`GENTE_STRESS_N`, `GENTE_STRESS_CHUNK`, `GENTE_STRESS_COMP`, `GENTE_STRESS_AUDIT`).
 *
 * **Timeline ponto (opcional):** com `GENTE_TIMELINE_SEED_MONTHS` entre 1 e 24, após o stress (se houver) corre-se
 * {@see GenteTimelineCoverageSeeder} (batidas mínimas em `REGISTRO_PONTO` — homolog sem 90k×78 views).
 *
 * Alinhamento com as ~78 rotas do manifesto em `resources/gente-v3/src/navigation/navManifest.js`:
 * não existe uma tabela “1:1 por aba”; a cobertura vem de `SidebarCoverageSeeder` + `SystemPhase2CoverageSeeder`
 * + `ConfigTabsCoverageSeeder` + `ErpFiscalCoverageSeeder` (execução/PCASP mínimos auditáveis) + organograma/folha/escala.
 * Abas puramente administrativas ou ERP podem ficar com listas vazias até seeds específicos — documentado em
 * `docs/davi/abas-sidebar.md` e matriz em `docs/davi/ONTOLOGIA_78_ABAS.md`.
 */
class SecretariasSeed extends Seeder
{
    public function run(): void
    {
        $this->command?->info('SECRETARIAS-SEED: início (São Luís / PMSLz + cobertura sidebar).');

        $this->call([
            TabelaGenericaSeeder::class,
            MotivoAlteracaoDominioSeeder::class,
            RbacMatrixSeeder::class,
            PccvDominioSeeder::class,
            PerfilSeeder::class,
            ConfiguracaoSistemaSeeder::class,
            MenuSeeder::class,
            UsuarioSeeder::class,
            RubricasCatalogoSeeder::class,
            VinculosPMSLzSeeder::class,
            OrganogramaPMSLzSeeder::class,
            TabelaSalarialPMSLzSeeder::class,
            FuncionariosPMSLzSeeder::class,
            SaudeEBeneficiosCoverageSeeder::class,
            SubstituicaoEscalaSeeder::class,
            UsuariosPMSLzSeeder::class,
            PcaspSeeder::class,
            SagresDeParaSeeder::class,
            SidebarCoverageSeeder::class,
            SystemPhase2CoverageSeeder::class,
            ErpFiscalCoverageSeeder::class,
            Feriados2026Seeder::class,
            ConfigTabsCoverageSeeder::class,
        ]);

        if (filter_var(env('GENTE_STRESS_SEED', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->command?->warn('SECRETARIAS-SEED: GENTE_STRESS_SEED=1 — a executar SuperSeederEstresseMigracao (pode demorar).');
            $this->call(SuperSeederEstresseMigracao::class);
        }

        $timelineMonths = max(0, min(24, (int) env('GENTE_TIMELINE_SEED_MONTHS', 0)));
        if ($timelineMonths > 0) {
            $this->command?->warn("SECRETARIAS-SEED: GENTE_TIMELINE_SEED_MONTHS={$timelineMonths} — cobertura temporal (ponto).");
            $this->call(GenteTimelineCoverageSeeder::class);
        }

        $this->command?->info('SECRETARIAS-SEED: concluído.');
    }
}
