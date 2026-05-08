<?php

namespace Database\Seeders;

use App\Domain\Escala\EscalaWorkflowStatus;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convites de substituição alinhados à realidade SEMED/SEMUS (São Luís):
 * UEBs e Hospital Djalma Marques, justificativas (dobra de turno, licença médica)
 * e vínculo de cargo a PCCV_DOMINIO quando disponível.
 *
 * Depende de OrganogramaPMSLzSeeder + FuncionariosPMSLzSeeder (matrículas e setores)
 * e de PccvDominioSeeder (domínio jurídico).
 */
class SubstituicaoEscalaSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('SUBSTITUICAO_ESCALA') || ! Schema::hasTable('ESCALA')) {
            return;
        }

        $semEdId = DB::table('UNIDADE')->where('UNIDADE_SIGLA', 'SEMED')->value('UNIDADE_ID');
        if (! $semEdId) {
            return;
        }

        $setorId = DB::table('SETOR')->where('UNIDADE_ID', $semEdId)->orderBy('SETOR_ID')->value('SETOR_ID');
        if (! $setorId) {
            return;
        }

        $competencia = Carbon::now()->format('Y-m');
        $colsEscala = Schema::getColumnListing('ESCALA');
        $escala = DB::table('ESCALA')
            ->where('SETOR_ID', $setorId)
            ->where('ESCALA_COMPETENCIA', $competencia)
            ->first();

        if (! $escala) {
            $dadosEscala = [
                'ESCALA_COMPETENCIA' => $competencia,
                'SETOR_ID' => $setorId,
            ];
            if (in_array('ESCALA_STATUS', $colsEscala, true)) {
                $dadosEscala['ESCALA_STATUS'] = EscalaWorkflowStatus::RASCUNHO;
            }
            if (in_array('ESCALA_ATIVO', $colsEscala, true)) {
                $dadosEscala['ESCALA_ATIVO'] = 1;
            }
            if (in_array('ESCALA_OBSERVACAO', $colsEscala, true)) {
                $dadosEscala['ESCALA_OBSERVACAO'] = 'Escala SEMED — template São Luís (SubstituicaoEscalaSeeder)';
            }
            if (in_array('created_at', $colsEscala, true)) {
                $dadosEscala['created_at'] = now();
            }
            if (in_array('updated_at', $colsEscala, true)) {
                $dadosEscala['updated_at'] = now();
            }
            $escalaId = (int) DB::table('ESCALA')->insertGetId($dadosEscala);
        } else {
            $escalaId = (int) $escala->ESCALA_ID;
        }

        $porMatricula = fn (string $mat) => (int) (DB::table('FUNCIONARIO')->where('FUNCIONARIO_MATRICULA', $mat)->value('FUNCIONARIO_ID') ?? 0);

        $raimundo = $porMatricula('2007-0006');
        $luciana = $porMatricula('2011-0007');
        $anaPaula = $porMatricula('2024-0015');

        if ($raimundo <= 0 || $luciana <= 0) {
            return;
        }

        $this->vincularCargosPccvAFuncionariosExemplo(
            $raimundo,
            $luciana,
            $anaPaula > 0 ? $anaPaula : null
        );

        $colsSub = Schema::getColumnListing('SUBSTITUICAO_ESCALA');

        $candidatos = [
            [
                'FUNCIONARIO_ID' => $luciana,
                'FUNCIONARIO_SUBSTITUTO_ID' => $anaPaula > 0 ? $anaPaula : $raimundo,
                'SUBSTITUICAO_ESCALA_DATA' => Carbon::now()->addDays(5)->toDateString(),
                'SUBSTITUICAO_ESCALA_JUSTIFICATIVA' => 'Substituição por licença médica (atestado e CRM em anexo) — Dobra de turno na U.E.B. para manter o calendário escolar.',
                'SUBSTITUICAO_ESCALA_STATUS' => 'pendente_aceite',
                'TIPO_CONVOCACAO' => 'OPTATIVA',
                'HORARIO_INICIO' => '07:30',
                'HORARIO_FIM' => '12:30',
                'UNIDADE_ESCOLAR' => 'U.E.B. Alberto Pinheiro',
                'DISCIPLINA_CARGO' => 'Professor de Ensino Fundamental (PCCV Magistério — Lei nº 4.928/2008)',
            ],
            [
                'FUNCIONARIO_ID' => $raimundo,
                'FUNCIONARIO_SUBSTITUTO_ID' => $luciana,
                'SUBSTITUICAO_ESCALA_DATA' => Carbon::now()->addDays(8)->toDateString(),
                'SUBSTITUICAO_ESCALA_JUSTIFICATIVA' => 'Dobra de turno: cobertura de carência temporária no contra-turno, conforme reunião da Superintendência de Ensino — U.E.B. Barbosa de Godóis.',
                'SUBSTITUICAO_ESCALA_STATUS' => 'pendente_aceite',
                'TIPO_CONVOCACAO' => 'OPTATIVA',
                'HORARIO_INICIO' => '13:00',
                'HORARIO_FIM' => '18:00',
                'UNIDADE_ESCOLAR' => 'U.E.B. Professora Barbosa de Godóis',
                'DISCIPLINA_CARGO' => 'Gestor Escolar (Estatuto Geral / Regime comum — Lei nº 4.615/2006)',
            ],
            [
                'FUNCIONARIO_ID' => $raimundo,
                'FUNCIONARIO_SUBSTITUTO_ID' => $anaPaula > 0 ? $anaPaula : $luciana,
                'SUBSTITUICAO_ESCALA_DATA' => Carbon::now()->subDays(12)->toDateString(),
                'SUBSTITUICAO_ESCALA_JUSTIFICATIVA' => 'Cobertura de auxiliar em licença médica — CI nº 45/2026. Substituição por licença médica; plantão 12h na U.E.B. (coordenação com a SEMED).',
                'SUBSTITUICAO_ESCALA_STATUS' => 'confirmada',
                'TIPO_CONVOCACAO' => 'OPTATIVA',
                'HORARIO_INICIO' => '08:00',
                'HORARIO_FIM' => '17:00',
                'UNIDADE_ESCOLAR' => 'U.E.B. Alberto Pinheiro',
                'DISCIPLINA_CARGO' => 'Auxiliar de Serviços Gerais (Regime geral vinculado à escola)',
            ],
        ];

        if ($anaPaula > 0) {
            $candidatos[] = [
                'FUNCIONARIO_ID' => $anaPaula,
                'FUNCIONARIO_SUBSTITUTO_ID' => $luciana,
                'SUBSTITUICAO_ESCALA_DATA' => Carbon::now()->addDays(15)->toDateString(),
                'SUBSTITUICAO_ESCALA_JUSTIFICATIVA' => 'Substituição por licença médica (equipe hospitalar) — reforço de enfermagem no Hospital Djalma Marques (HDM) em turno 12h; PPCV Saúde (Lei nº 4.616/2006). Dobra de turno autorizada.',
                'SUBSTITUICAO_ESCALA_STATUS' => 'pendente_aceite',
                'TIPO_CONVOCACAO' => 'OPTATIVA',
                'HORARIO_INICIO' => '07:00',
                'HORARIO_FIM' => '19:00',
                'UNIDADE_ESCOLAR' => 'Hospital Djalma Marques (HDM) — atenção em regime de plantão',
                'DISCIPLINA_CARGO' => 'Técnico(a) em Enfermagem (PCCV Profissionais da Saúde)',
            ];
        }

        foreach ($candidatos as $row) {
            $payload = ['ESCALA_ID' => $escalaId];
            foreach ($row as $col => $val) {
                if (in_array($col, $colsSub, true)) {
                    $payload[$col] = $val;
                }
            }
            if (isset($payload['SUBSTITUICAO_ESCALA_STATUS']) && in_array('STATUS', $colsSub, true) && ! isset($payload['STATUS'])) {
                $payload['STATUS'] = $payload['SUBSTITUICAO_ESCALA_STATUS'];
            }

            if (! isset($payload['FUNCIONARIO_ID'], $payload['SUBSTITUICAO_ESCALA_DATA'])) {
                continue;
            }

            $exists = DB::table('SUBSTITUICAO_ESCALA')
                ->where('ESCALA_ID', $escalaId)
                ->where('FUNCIONARIO_ID', $payload['FUNCIONARIO_ID'])
                ->where('SUBSTITUICAO_ESCALA_DATA', $payload['SUBSTITUICAO_ESCALA_DATA'])
                ->exists();
            if ($exists) {
                continue;
            }

            DB::table('SUBSTITUICAO_ESCALA')->insert($payload);
        }
    }

    /**
     * Ajusta CARGO.PCCV_ID (quando a coluna existe) e FUNCIONARIO.CARGO_ID para espelho jurídico.
     */
    private function vincularCargosPccvAFuncionariosExemplo(int $idGestor, int $idProfessor, ?int $idSaude): void
    {
        if (! Schema::hasTable('PCCV_DOMINIO') || ! Schema::hasTable('CARGO')) {
            return;
        }

        $funcCols = Schema::getColumnListing('FUNCIONARIO');
        if (! in_array('CARGO_ID', $funcCols, true)) {
            return;
        }

        $pccvMag = DB::table('PCCV_DOMINIO')->where('SIGLA', 'MAGISTERIO')->value('PCCV_DOMINIO_ID');
        $pccvGeral = DB::table('PCCV_DOMINIO')->where('SIGLA', 'GERAL')->value('PCCV_DOMINIO_ID');
        $pccvSaude = DB::table('PCCV_DOMINIO')->where('SIGLA', 'SAUDE')->value('PCCV_DOMINIO_ID');
        if (! $pccvMag || ! $pccvGeral) {
            return;
        }

        $cargoCols = Schema::getColumnListing('CARGO');
        $temPccvNoCargo = in_array('PCCV_ID', $cargoCols, true);

        $cargoProf = $this->garantirCargoComPccv(
            'Professor de Ensino Fundamental (seed GENTE — Magistério / SEMED)',
            (int) $pccvMag,
            $cargoCols
        );
        $cargoGest = $this->garantirCargoComPccv(
            'Agente de Gestão Escolar (seed GENTE — Regime Geral / SEMED)',
            (int) $pccvGeral,
            $cargoCols
        );
        $cargoSaude = null;
        if ($pccvSaude) {
            $cargoSaude = $this->garantirCargoComPccv(
                'Técnico em Enfermagem (seed GENTE — HDM / SEMUS)',
                (int) $pccvSaude,
                $cargoCols
            );
        }

        if ($cargoGest) {
            DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $idGestor)->update(['CARGO_ID' => $cargoGest]);
        }
        if ($cargoProf) {
            DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $idProfessor)->update(['CARGO_ID' => $cargoProf]);
        }
        if ($idSaude && $cargoSaude) {
            DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $idSaude)->update(['CARGO_ID' => $cargoSaude]);
        }
    }

    /**
     * @param  list<string>  $cargoCols
     */
    private function garantirCargoComPccv(string $nomeCargo, int $pccvDominioId, array $cargoCols): ?int
    {
        if (! in_array('CARGO_NOME', $cargoCols, true)) {
            return null;
        }

        $payload = ['CARGO_NOME' => $nomeCargo];
        if (in_array('CARGO_ATIVO', $cargoCols, true)) {
            $payload['CARGO_ATIVO'] = 1;
        }
        if (in_array('CARGO_DATA_INICIO', $cargoCols, true)) {
            $payload['CARGO_DATA_INICIO'] = '2006-01-01';
        }
        if (in_array('PCCV_ID', $cargoCols, true)) {
            $payload['PCCV_ID'] = $pccvDominioId;
        }
        if (in_array('created_at', $cargoCols, true)) {
            $payload['created_at'] = now();
        }
        if (in_array('updated_at', $cargoCols, true)) {
            $payload['updated_at'] = now();
        }

        DB::table('CARGO')->updateOrInsert(
            ['CARGO_NOME' => $nomeCargo],
            $payload
        );

        return (int) (DB::table('CARGO')->where('CARGO_NOME', $nomeCargo)->value('CARGO_ID') ?? 0) ?: null;
    }
}
