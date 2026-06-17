<?php

namespace App\Domain\Escala;

use App\Models\Usuario;
use App\MyLibs\PerfilEnum;
use App\Support\GenteAuditWriter;
use App\Support\GenteSudoGlobalView;
use App\Support\RbacResolver;
use App\Support\UnidadeEscopoUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class EscalaWorkflowService
{
    public const ACAO_ENVIAR = 'enviar_validacao';

    public const ACAO_REENVIAR = 'reenviar_validacao';

    public const ACAO_DEVOLVER = 'devolver_ajuste';

    public const ACAO_HOMOLOGAR = 'homologar';

    /** Auditoria: edição de célula na grade quando o cabeçalho estava fora de RASCUNHO/DEVOLVIDA e só foi permitida por Sudo (break-glass). */
    public const ACAO_INTERVENCAO_TECNICA_GRADE = 'ESCALA_INTERVENCAO_SUDO_GRADE';

    /**
     * @return list<int>
     */
    public static function perfisAtivosUsuario(?Usuario $user): array
    {
        if (! $user) {
            return [];
        }
        $uid = (int) $user->getAttribute('USUARIO_ID');
        if ($uid <= 0 || ! Schema::hasTable('USUARIO_PERFIL')) {
            return [];
        }
        $q = DB::table('USUARIO_PERFIL')->where('USUARIO_ID', $uid);
        if (Schema::hasColumn('USUARIO_PERFIL', 'USUARIO_PERFIL_ATIVO')) {
            $q->where('USUARIO_PERFIL_ATIVO', 1);
        }

        return $q->pluck('PERFIL_ID')->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    public static function bypassAdministrativo(?Usuario $user, ?Request $request): bool
    {
        if (! $user || ! $request) {
            return false;
        }

        return GenteSudoGlobalView::podeUsarVisaoGlobal($user, $request);
    }

    private static function rbacPodeEscala(int $usuarioId, string $permSlug): bool
    {
        if ($usuarioId <= 0 || ! Schema::hasTable('GENTE_ASSIGNMENT')) {
            return false;
        }

        return (new RbacResolver())->can($usuarioId, $permSlug);
    }

    private static function usuarioIdAutenticadoParaRbac(): int
    {
        $u = Auth::user();

        return $u instanceof Usuario ? (int) $u->getAttribute('USUARIO_ID') : 0;
    }

    /**
     * @param  list<int>  $perfis
     */
    public static function usuarioTemAlgumPerfil(array $perfis, array $ids): bool
    {
        foreach ($ids as $id) {
            if (in_array((int) $id, $perfis, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<int>  $perfis
     */
    public static function podeEnviarValidacao(array $perfis, bool $bypass): bool
    {
        if ($bypass) {
            return true;
        }
        $uid = self::usuarioIdAutenticadoParaRbac();
        if (self::rbacPodeEscala($uid, 'escala.grade.editar')) {
            return true;
        }

        return self::usuarioTemAlgumPerfil($perfis, [
            PerfilEnum::COORD_DE_SETOR,
            PerfilEnum::DIRETOR_GESTOR_UND,
            PerfilEnum::GESTAO,
            PerfilEnum::RH_UNIDADE,
            PerfilEnum::ADMINISTRADOR,
            PerfilEnum::DESENVOLVEDOR,
        ]);
    }

    /**
     * Superintendência / revisão institucional (devolução).
     *
     * @param  list<int>  $perfis
     */
    public static function podeDevolverParaAjuste(array $perfis, bool $bypass): bool
    {
        if ($bypass) {
            return true;
        }
        $uid = self::usuarioIdAutenticadoParaRbac();
        if (self::rbacPodeEscala($uid, 'escala.workflow.devolver')) {
            return true;
        }

        return self::usuarioTemAlgumPerfil($perfis, [
            PerfilEnum::GESTAO,
            PerfilEnum::RH_APS,
            PerfilEnum::RH_REDE,
            PerfilEnum::ADMINISTRADOR,
            PerfilEnum::DESENVOLVEDOR,
        ]);
    }

    /**
     * @param  list<int>  $perfis
     */
    public static function podeHomologarSagep(array $perfis, bool $bypass): bool
    {
        if ($bypass) {
            return true;
        }
        $uid = self::usuarioIdAutenticadoParaRbac();
        if (self::rbacPodeEscala($uid, 'escala.workflow.homologar')) {
            return true;
        }

        return self::usuarioTemAlgumPerfil($perfis, [
            PerfilEnum::EQUIPE_SISGEP,
            PerfilEnum::ADMINISTRADOR,
            PerfilEnum::DESENVOLVEDOR,
        ]);
    }

    public static function assertSetorAutorizado(?Usuario $user, int $setorId, Request $request): void
    {
        UnidadeEscopoUsuario::abortoSeSetorNaoAutorizado($user, $setorId, $request);
    }

    /**
     * @return array{from: string, to: string}
     */
    public static function resolverTransicao(string $statusAtual, string $acao): array
    {
        $s = strtoupper(trim($statusAtual));
        $a = strtolower(trim($acao));

        return match ($a) {
            self::ACAO_ENVIAR => match ($s) {
                EscalaWorkflowStatus::RASCUNHO => [
                    'from' => EscalaWorkflowStatus::RASCUNHO,
                    'to' => EscalaWorkflowStatus::EM_VALIDACAO_SUPERINTENDENCIA,
                ],
                default => throw new \RuntimeException('Transição inválida: só é possível enviar em RASCUNHO.'),
            },
            self::ACAO_REENVIAR => match ($s) {
                EscalaWorkflowStatus::DEVOLVIDA_PARA_AJUSTE => [
                    'from' => EscalaWorkflowStatus::DEVOLVIDA_PARA_AJUSTE,
                    'to' => EscalaWorkflowStatus::EM_VALIDACAO_SUPERINTENDENCIA,
                ],
                default => throw new \RuntimeException('Transição inválida: só é possível reenviar após devolução.'),
            },
            self::ACAO_DEVOLVER => match ($s) {
                EscalaWorkflowStatus::EM_VALIDACAO_SUPERINTENDENCIA => [
                    'from' => EscalaWorkflowStatus::EM_VALIDACAO_SUPERINTENDENCIA,
                    'to' => EscalaWorkflowStatus::DEVOLVIDA_PARA_AJUSTE,
                ],
                default => throw new \RuntimeException('Transição inválida: devolução só a partir de EM_VALIDACAO.'),
            },
            self::ACAO_HOMOLOGAR => match ($s) {
                EscalaWorkflowStatus::EM_VALIDACAO_SUPERINTENDENCIA => [
                    'from' => EscalaWorkflowStatus::EM_VALIDACAO_SUPERINTENDENCIA,
                    'to' => EscalaWorkflowStatus::HOMOLOGADO_SAGEP,
                ],
                default => throw new \RuntimeException('Transição inválida: homologação só a partir de EM_VALIDACAO.'),
            },
            default => throw new \RuntimeException('Ação de workflow desconhecida.'),
        };
    }

    /**
     * @param  array<string, mixed>  $escalaRow  linha da tabela ESCALA
     */
    public static function assertPodeEditarGrade(array $escalaRow, ?Request $request = null): void
    {
        $st = self::normalizarStatusLeitura($escalaRow['ESCALA_STATUS'] ?? $escalaRow['escala_status'] ?? null);
        if (EscalaWorkflowStatus::permiteEdicaoGrade($st)) {
            return;
        }
        $user = Auth::user();
        if ($request && $user instanceof Usuario && self::bypassAdministrativo($user, $request)) {
            return;
        }
        throw new \RuntimeException('Escala bloqueada para edição: status '.$st.'.');
    }

    /**
     * Indica se esta edição de grade deve ser auditada como intervenção Sudo (status trancado + bypass ativo).
     *
     * @param  \stdClass|array<string, mixed>  $escalaCab
     * @return array{bypass_usado: bool, status_normalizado: string}
     */
    public static function contextoIntervencaoGrade(?Request $request, $escalaCab): array
    {
        if (! $request || ! $escalaCab) {
            return ['bypass_usado' => false, 'status_normalizado' => ''];
        }
        $row = self::rowToArray($escalaCab);
        $st = self::normalizarStatusLeitura($row['ESCALA_STATUS'] ?? null);
        $user = Auth::user();
        $bypassUsado = ! EscalaWorkflowStatus::permiteEdicaoGrade($st)
            && $user instanceof Usuario
            && self::bypassAdministrativo($user, $request);

        return ['bypass_usado' => $bypassUsado, 'status_normalizado' => $st];
    }

    public static function registrarAuditoriaWorkflow(
        Request $request,
        int $escalaId,
        int $setorId,
        string $competencia,
        string $acao,
        string $de,
        string $para,
        bool $bypassAdministrativo,
        ?string $motivoDevolucao = null
    ): void {
        if (! Schema::hasTable('AUDIT_LOG')) {
            return;
        }
        $uid = (int) GenteAuditWriter::requireAuthenticatedUserId();
        $ctxPayload = [
            'escala_id' => $escalaId,
            'setor_id' => $setorId,
            'competencia' => $competencia,
            'acao' => $acao,
            'status_anterior' => $de,
            'status_novo' => $para,
            'bypass_administrativo' => $bypassAdministrativo,
            'motivo_devolucao' => $motivoDevolucao,
        ];
        if ($bypassAdministrativo) {
            $auth = Auth::user();
            if ($auth instanceof Usuario) {
                $resolver = new RbacResolver();
                $aid = $resolver->assignmentIdParaIntervencaoGrade((int) $auth->getAttribute('USUARIO_ID'), $request);
                if ($aid !== null) {
                    $ctxPayload['gente_assignment_id'] = $aid;
                }
            }
        }
        $ctx = json_encode($ctxPayload, JSON_UNESCAPED_UNICODE);

        $cols = Schema::getColumnListing('AUDIT_LOG');
        $byLower = [];
        foreach ($cols as $c) {
            $byLower[strtolower($c)] = $c;
        }
        $pick = static function (string ...$names) use ($byLower): ?string {
            foreach ($names as $name) {
                $k = $byLower[strtolower($name)] ?? null;
                if ($k !== null) {
                    return $k;
                }
            }

            return null;
        };
        $row = [];
        if ($c = $pick('ACAO', 'acao')) {
            $row[$c] = 'ESCALA_WORKFLOW_TRANSITION';
        }
        if ($c = $pick('TABELA', 'tabela')) {
            $row[$c] = 'ESCALA';
        }
        if ($c = $pick('DADOS_NOVOS', 'dados_novos', 'dados', 'contexto')) {
            $row[$c] = $ctx;
        } elseif ($c = $pick('DADOS_ANTIGOS', 'dados_antigos')) {
            $row[$c] = $ctx;
        }
        if ($c = $pick('USUARIO_ID', 'usuario_id', 'USER_ID', 'user_id')) {
            $row[$c] = $uid;
        }
        if ($c = $pick('IP')) {
            $row[$c] = (string) $request->ip();
        }
        if ($c = $pick('USER_AGENT', 'user_agent')) {
            $row[$c] = Str::limit((string) $request->userAgent(), 255, '');
        }
        if (! empty($row)) {
            GenteAuditWriter::insertChainedRow($row);
        }
    }

    /**
     * @param  \stdClass|array<string, mixed>  $escala
     */
    public static function rowToArray($escala): array
    {
        if ($escala instanceof \stdClass) {
            return (array) $escala;
        }

        return is_array($escala) ? $escala : [];
    }

    public static function normalizarStatusLeitura(?string $status): string
    {
        $s = strtoupper(trim((string) $status));
        if ($s === '' || $s === 'ABERTA' || $s === 'ABERTO' || $s === 'RASCUNHO' || $s === 'FECHADA' || $s === 'FECHADO') {
            return EscalaWorkflowStatus::RASCUNHO;
        }

        return $s;
    }

    /**
     * @return array<string, string>
     */
    public static function rotulosHumanos(): array
    {
        return [
            EscalaWorkflowStatus::RASCUNHO => 'Em preenchimento',
            EscalaWorkflowStatus::EM_VALIDACAO_SUPERINTENDENCIA => 'Aguardando validação (Superintendência)',
            EscalaWorkflowStatus::DEVOLVIDA_PARA_AJUSTE => 'Devolvida para ajuste',
            EscalaWorkflowStatus::HOMOLOGADO_SAGEP => 'Homologada (SAGEP)',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function montarPayloadApi(?\stdClass $cab, int $setorId, Request $request): ?array
    {
        $user = Auth::user();
        $perfis = self::perfisAtivosUsuario($user instanceof Usuario ? $user : null);
        $bypass = self::bypassAdministrativo($user instanceof Usuario ? $user : null, $request);
        $rotulos = self::rotulosHumanos();

        if (! $cab) {
            $st = EscalaWorkflowStatus::RASCUNHO;

            return [
                'escala_id' => null,
                'setor_id' => $setorId,
                'status' => $st,
                'status_label' => $rotulos[$st] ?? $st,
                'motivo_devolucao' => null,
                'devolvido_em' => null,
                'devolvido_por_nome' => null,
                'pode_editar_grade_sudo' => $bypass,
                'permissoes' => [
                    'enviar_validacao' => ($st === EscalaWorkflowStatus::RASCUNHO) && self::podeEnviarValidacao($perfis, $bypass),
                    'reenviar_validacao' => false,
                    'devolver_ajuste' => false,
                    'homologar' => false,
                ],
            ];
        }
        $st = self::normalizarStatusLeitura($cab->ESCALA_STATUS ?? null);
        $devNome = null;
        if (Schema::hasColumn('ESCALA', 'ESCALA_DEVOLVIDA_POR') && ! empty($cab->ESCALA_DEVOLVIDA_POR)) {
            $devNome = DB::table('USUARIO')->where('USUARIO_ID', (int) $cab->ESCALA_DEVOLVIDA_POR)->value('USUARIO_NOME');
        }
        return [
            'escala_id' => (int) $cab->ESCALA_ID,
            'setor_id' => $setorId,
            'status' => $st,
            'status_label' => $rotulos[$st] ?? $st,
            'motivo_devolucao' => Schema::hasColumn('ESCALA', 'ESCALA_MOTIVO_DEVOLUCAO') ? ($cab->ESCALA_MOTIVO_DEVOLUCAO ?? null) : null,
            'devolvido_em' => Schema::hasColumn('ESCALA', 'ESCALA_DEVOLVIDA_EM') ? ($cab->ESCALA_DEVOLVIDA_EM ?? null) : null,
            'devolvido_por_nome' => $devNome,
            'pode_editar_grade_sudo' => $bypass,
            'permissoes' => [
                'enviar_validacao' => ($st === EscalaWorkflowStatus::RASCUNHO) && self::podeEnviarValidacao($perfis, $bypass),
                'reenviar_validacao' => ($st === EscalaWorkflowStatus::DEVOLVIDA_PARA_AJUSTE) && self::podeEnviarValidacao($perfis, $bypass),
                'devolver_ajuste' => ($st === EscalaWorkflowStatus::EM_VALIDACAO_SUPERINTENDENCIA) && self::podeDevolverParaAjuste($perfis, $bypass),
                'homologar' => ($st === EscalaWorkflowStatus::EM_VALIDACAO_SUPERINTENDENCIA) && self::podeHomologarSagep($perfis, $bypass),
            ],
        ];
    }

    /**
     * Payload mínimo de workflow na visão macro (sem setor): só flags globais (ex.: Sudo na grade).
     *
     * @return array<string, mixed>
     */
    public static function montarPayloadWorkflowMacro(Request $request): array
    {
        $user = Auth::user();
        $bypass = self::bypassAdministrativo($user instanceof Usuario ? $user : null, $request);

        return [
            'escala_id' => null,
            'setor_id' => null,
            'status' => null,
            'status_label' => 'Visão macro (vários setores)',
            'motivo_devolucao' => null,
            'devolvido_em' => null,
            'devolvido_por_nome' => null,
            'pode_editar_grade_sudo' => $bypass,
            'permissoes' => [
                'enviar_validacao' => false,
                'reenviar_validacao' => false,
                'devolver_ajuste' => false,
                'homologar' => false,
            ],
        ];
    }

    /**
     * Processa transição de workflow na cabeça ESCALA (setor + competência).
     *
     * @throws \RuntimeException
     */
    public static function processarTransicao(
        Request $request,
        string $competenciaYm,
        int $setorId,
        string $acao,
        ?string $motivoDevolucao
    ): array {
        $user = Auth::user();
        if (! $user instanceof Usuario) {
            throw new \RuntimeException('Sessão inválida.');
        }
        self::assertSetorAutorizado($user, $setorId, $request);

        $perfis = self::perfisAtivosUsuario($user);
        $bypass = self::bypassAdministrativo($user, $request);
        $acao = strtolower(trim($acao));

        return DB::transaction(function () use ($request, $competenciaYm, $setorId, $acao, $motivoDevolucao, $perfis, $bypass, $user) {
            $cab = DB::table('ESCALA')
                ->where('ESCALA_COMPETENCIA', $competenciaYm)
                ->where('SETOR_ID', $setorId)
                ->first();

            if (! $cab && $acao === self::ACAO_ENVIAR) {
                $insert = [
                    'ESCALA_COMPETENCIA' => $competenciaYm,
                    'SETOR_ID' => $setorId,
                ];
                if (Schema::hasColumn('ESCALA', 'ESCALA_STATUS')) {
                    $insert['ESCALA_STATUS'] = EscalaWorkflowStatus::RASCUNHO;
                }
                if (Schema::hasColumn('ESCALA', 'ESCALA_ATIVO')) {
                    $insert['ESCALA_ATIVO'] = 1;
                }
                if (Schema::hasColumn('ESCALA', 'ESCALA_OBSERVACAO')) {
                    $insert['ESCALA_OBSERVACAO'] = 'Criada via workflow de escala';
                }
                if (Schema::hasColumn('ESCALA', 'created_at')) {
                    $insert['created_at'] = now();
                }
                if (Schema::hasColumn('ESCALA', 'updated_at')) {
                    $insert['updated_at'] = now();
                }
                $eid = (int) DB::table('ESCALA')->insertGetId($insert);
                $cab = DB::table('ESCALA')->where('ESCALA_ID', $eid)->first();
            }
            if (! $cab) {
                throw new \RuntimeException('Cabeçalho de escala não encontrado para este setor e competência.');
            }

            $st = self::normalizarStatusLeitura($cab->ESCALA_STATUS ?? null);

            if ($acao === self::ACAO_ENVIAR) {
                if (! self::podeEnviarValidacao($perfis, $bypass)) {
                    throw new \RuntimeException('Sem permissão para enviar a escala para validação.');
                }
            } elseif ($acao === self::ACAO_REENVIAR) {
                if (! self::podeEnviarValidacao($perfis, $bypass)) {
                    throw new \RuntimeException('Sem permissão para reenviar a escala.');
                }
            } elseif ($acao === self::ACAO_DEVOLVER) {
                if (! self::podeDevolverParaAjuste($perfis, $bypass)) {
                    throw new \RuntimeException('Sem permissão para devolver a escala.');
                }
            } elseif ($acao === self::ACAO_HOMOLOGAR) {
                if (! self::podeHomologarSagep($perfis, $bypass)) {
                    throw new \RuntimeException('Sem permissão para homologar a escala (SAGEP).');
                }
            } else {
                throw new \RuntimeException('Ação inválida.');
            }

            if ($acao === self::ACAO_DEVOLVER) {
                $motivoDevolucao = trim((string) $motivoDevolucao);
                if ($motivoDevolucao === '') {
                    throw new \RuntimeException('Motivo da devolução é obrigatório.');
                }
            }

            $trans = self::resolverTransicao($st, $acao);
            $uid = (int) GenteAuditWriter::requireAuthenticatedUserId();

            $updates = [];
            if (Schema::hasColumn('ESCALA', 'ESCALA_STATUS')) {
                $updates['ESCALA_STATUS'] = $trans['to'];
            }
            if (Schema::hasColumn('ESCALA', 'updated_at')) {
                $updates['updated_at'] = now();
            }

            if ($acao === self::ACAO_ENVIAR || $acao === self::ACAO_REENVIAR) {
                if (Schema::hasColumn('ESCALA', 'ESCALA_ENVIADA_EM')) {
                    $updates['ESCALA_ENVIADA_EM'] = now();
                }
                if (Schema::hasColumn('ESCALA', 'ESCALA_ENVIADA_POR')) {
                    $updates['ESCALA_ENVIADA_POR'] = $uid;
                }
                if ($acao === self::ACAO_REENVIAR) {
                    if (Schema::hasColumn('ESCALA', 'ESCALA_MOTIVO_DEVOLUCAO')) {
                        $updates['ESCALA_MOTIVO_DEVOLUCAO'] = null;
                    }
                    if (Schema::hasColumn('ESCALA', 'ESCALA_DEVOLVIDA_EM')) {
                        $updates['ESCALA_DEVOLVIDA_EM'] = null;
                    }
                    if (Schema::hasColumn('ESCALA', 'ESCALA_DEVOLVIDA_POR')) {
                        $updates['ESCALA_DEVOLVIDA_POR'] = null;
                    }
                }
            }

            if ($acao === self::ACAO_DEVOLVER) {
                if (Schema::hasColumn('ESCALA', 'ESCALA_MOTIVO_DEVOLUCAO')) {
                    $updates['ESCALA_MOTIVO_DEVOLUCAO'] = $motivoDevolucao;
                }
                if (Schema::hasColumn('ESCALA', 'ESCALA_DEVOLVIDA_EM')) {
                    $updates['ESCALA_DEVOLVIDA_EM'] = now();
                }
                if (Schema::hasColumn('ESCALA', 'ESCALA_DEVOLVIDA_POR')) {
                    $updates['ESCALA_DEVOLVIDA_POR'] = $uid;
                }
            }

            if ($acao === self::ACAO_HOMOLOGAR) {
                if (Schema::hasColumn('ESCALA', 'ESCALA_HOMOLOGADA_EM')) {
                    $updates['ESCALA_HOMOLOGADA_EM'] = now();
                }
                if (Schema::hasColumn('ESCALA', 'ESCALA_HOMOLOGADA_POR')) {
                    $updates['ESCALA_HOMOLOGADA_POR'] = $uid;
                }
            }

            DB::table('ESCALA')->where('ESCALA_ID', (int) $cab->ESCALA_ID)->update($updates);

            self::registrarAuditoriaWorkflow(
                $request,
                (int) $cab->ESCALA_ID,
                $setorId,
                $competenciaYm,
                $acao,
                $trans['from'],
                $trans['to'],
                $bypass,
                $acao === self::ACAO_DEVOLVER ? $motivoDevolucao : null
            );

            $cabNova = DB::table('ESCALA')->where('ESCALA_ID', (int) $cab->ESCALA_ID)->first();

            return [
                'ok' => true,
                'workflow' => self::montarPayloadApi($cabNova, $setorId, $request),
            ];
        });
    }
}

