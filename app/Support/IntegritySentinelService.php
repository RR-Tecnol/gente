<?php

namespace App\Support;

use App\Http\Middleware\AlterarSenha;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class IntegritySentinelService
{
    public const CACHE_KEY = 'sentinel.health.v1';

    /** @return array<string, mixed> */
    public static function run(bool $persist = true): array
    {
        $now = now()->toIso8601String();
        $checks = [
            'db_connection' => self::safeProbe('db_connection', fn () => self::probeDbConnection()),
            'auth_integrity' => self::safeProbe('auth_integrity', fn () => self::probeAuthIntegrity()),
            'security_guard' => self::safeProbe('security_guard', fn () => self::probeSecurityGuard()),
            'schema_integrity' => self::safeProbe('schema_integrity', fn () => self::checkCriticalTables()),
            'query_smoke' => self::safeProbe('query_smoke', fn () => self::probeQuerySmoke()),
            'business_integrity' => self::safeProbe('business_integrity', fn () => self::probeBusinessIntegrity()),
            'audit_trail_check' => self::safeProbe('audit_trail_check', fn () => self::probeAuditTrail()),
            'document_delivery_check' => self::safeProbe('document_delivery_check', fn () => self::probeDocumentDelivery()),
            'organogram_integrity' => self::safeProbe('organogram_integrity', fn () => self::probeOrganogramIntegrity()),
            'workforce_coherence' => self::safeProbe('workforce_coherence', fn () => self::probeWorkforceCoherence()),
        ];

        $hasCritical = collect($checks)->contains(function (array $p): bool {
            return ! (bool) ($p['ok'] ?? false);
        });
        $hasWarning = collect($checks)->contains(function (array $p): bool {
            return (bool) ($p['warning'] ?? false);
        });

        $payload = [
            'timestamp' => $now,
            'status' => $hasCritical ? 'critical' : ($hasWarning ? 'warning' : 'ok'),
            'checks' => $checks,
        ];

        if ($persist) {
            self::safePersist($payload);
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private static function safeProbe(string $name, callable $fn): array
    {
        try {
            $r = $fn();
            if (! is_array($r)) {
                return ['ok' => false, 'message' => "Probe {$name} retornou payload inválido"];
            }
            if (! array_key_exists('ok', $r)) {
                $r['ok'] = false;
            }

            return $r;
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => "Probe {$name} falhou: " . $e->getMessage(),
                'exception' => get_class($e),
            ];
        }
    }

    /** @return array<string, mixed> */
    public static function lastOrRun(): array
    {
        return Cache::get(self::CACHE_KEY) ?? self::run(true);
    }

    public static function recordInvalidObjectName(string $message): void
    {
        $data = self::lastOrRun();
        $data['status'] = 'critical';
        $data['checks']['schema_integrity'] = [
            'ok' => false,
            'message' => "Integridade de dados crítica: {$message}",
            'impacted_modules' => ['Minha Progressão', 'Portal do Gestor', 'Folha de Pagamento'],
        ];
        $data['last_query_exception'] = [
            'type' => 'invalid_object_name',
            'message' => $message,
            'at' => now()->toIso8601String(),
        ];
        self::safePersist($data);
    }

    /** @return array<string, mixed> */
    private static function probeDbConnection(): array
    {
        try {
            DB::select('SELECT 1 as ok');
            return ['ok' => true, 'message' => 'Banco respondendo'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Falha conexão DB: ' . $e->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    private static function probeAuthIntegrity(): array
    {
        try {
            $user = Usuario::query()
                ->where('USUARIO_ATIVO', 1)
                ->whereNotNull('USUARIO_LOGIN')
                ->whereRaw("USUARIO_LOGIN LIKE '%@%'")
                ->orderBy('USUARIO_ID')
                ->first();
            if (! $user) {
                return ['ok' => false, 'message' => 'Sem usuário com login e-mail para probe'];
            }

            $probe = '  ' . strtoupper((string) $user->USUARIO_LOGIN) . '  ';
            $normalized = LoginLookupNormalizer::forDatabaseLookup($probe);
            $resolved = UsuarioLoginResolver::resolveByNormalizedLogin($normalized);
            $ok = $resolved && (int) $resolved->USUARIO_ID === (int) $user->USUARIO_ID;

            return [
                'ok' => (bool) $ok,
                'message' => $ok ? 'Normalização de e-mail operacional' : 'Resolver não reconheceu login normalizado',
                'sample_login' => (string) $user->USUARIO_LOGIN,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Falha probe auth: ' . $e->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    private static function probeSecurityGuard(): array
    {
        $prev = Auth::user();
        try {
            $fake = new Usuario();
            $fake->USUARIO_ID = 0;
            $fake->USUARIO_LOGIN = 'sentinel@local';
            $fake->USUARIO_PRIMEIRO_ACESSO = 1;
            $fake->USUARIO_ALTERAR_SENHA = 0;

            Auth::setUser($fake);
            $mw = app(AlterarSenha::class);
            $req = Request::create('/api/v3/funcionarios', 'GET');
            $resp = $mw->handle($req, fn () => response()->json(['ok' => true], 200));
            $ok = $resp->getStatusCode() === 412;

            return [
                'ok' => $ok,
                'message' => $ok ? 'Middleware bloqueando corretamente (412)' : 'Middleware não bloqueou como esperado',
                'status_code' => $resp->getStatusCode(),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Falha probe segurança: ' . $e->getMessage()];
        } finally {
            if ($prev instanceof Usuario) {
                Auth::setUser($prev);
            } else {
                Auth::logout();
            }
        }
    }

    /** @return array<string, mixed> */
    private static function checkCriticalTables(): array
    {
        $critical = ['USUARIO', 'FUNCIONARIO', 'PROGRESSAO', 'VINCULO', 'FOLHA'];
        $missing = [];
        foreach ($critical as $tbl) {
            if (! self::tableExists($tbl)) {
                $missing[] = $tbl;
            }
        }
        if ($missing) {
            return [
                'ok' => false,
                'message' => 'Tabela(s) ausente(s): ' . implode(', ', $missing),
                'missing' => $missing,
                'impacted_modules' => self::impactedModulesForMissingTables($missing),
            ];
        }

        return ['ok' => true, 'message' => 'Tabelas críticas presentes'];
    }

    /** @return array<string, mixed> */
    private static function probeQuerySmoke(): array
    {
        $tables = ['USUARIO', 'FUNCIONARIO', 'PROGRESSAO', 'VINCULO', 'FOLHA'];
        try {
            $counts = [];
            foreach ($tables as $tbl) {
                if (! self::tableExists($tbl)) {
                    continue;
                }
                $counts[$tbl] = (int) DB::table($tbl)->count();
            }
            return ['ok' => true, 'message' => 'Smoke query OK', 'counts' => $counts];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Falha query smoke: ' . $e->getMessage()];
        }
    }

    /** @return array<string,mixed> */
    private static function probeAuditTrail(): array
    {
        try {
            $table = null;
            foreach (['AUDIT_LOG', 'LOG_AUDITORIA'] as $candidate) {
                if (self::tableExists($candidate)) {
                    $table = $candidate;
                    break;
                }
            }

            if (! $table) {
                return [
                    'ok' => true,
                    'warning' => true,
                    'message' => 'Trilha de auditoria indisponível (tabela ausente)',
                ];
            }

            $timeCol = self::firstExistingColumn($table, ['created_at', 'DATA_HORA', 'LOG_DATA', 'DATA']);
            $actionCol = self::firstExistingColumn($table, ['ACAO', 'EVENTO', 'OPERACAO', 'ACAO_NOME']);
            $query = DB::table($table);

            $total = (int) (clone $query)->count();
            $last24h = $timeCol
                ? (int) (clone $query)->where($timeCol, '>=', now()->subDay())->count()
                : 0;

            $sensitive24h = 0;
            if ($timeCol && $actionCol) {
                $sensitive24h = (int) (clone $query)
                    ->where($timeCol, '>=', now()->subDay())
                    ->where(function ($q) use ($actionCol) {
                        $q->where($actionCol, 'like', '%/api/v3/funcionarios%')
                            ->orWhere($actionCol, 'like', '%/api/v3/folha%')
                            ->orWhere($actionCol, 'like', '%/api/v3/progressao%')
                            ->orWhere($actionCol, 'like', '%/api/v3/gestor%');
                    })
                    ->count();
            }

            if ($last24h === 0) {
                return [
                    'ok' => true,
                    'warning' => true,
                    'message' => 'Sem registros de auditoria nas últimas 24h',
                    'table' => $table,
                    'total' => $total,
                    'last_24h' => $last24h,
                    'sensitive_24h' => $sensitive24h,
                ];
            }

            return [
                'ok' => true,
                'warning' => false,
                'message' => 'Trilha de auditoria ativa',
                'table' => $table,
                'total' => $total,
                'last_24h' => $last24h,
                'sensitive_24h' => $sensitive24h,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => true,
                'warning' => true,
                'message' => 'Falha ao validar trilha de auditoria: ' . $e->getMessage(),
            ];
        }
    }

    /** @return array<string,mixed> */
    private static function probeDocumentDelivery(): array
    {
        try {
            if (! self::tableExists('DOCUMENTOS_SERVIDOR')) {
                return [
                    'ok' => true,
                    'warning' => true,
                    'message' => 'Dossiê digital de portarias indisponível (tabela ausente)',
                ];
            }

            $base = DB::table('DOCUMENTOS_SERVIDOR')
                ->where('TIPO_DOCUMENTO', 'PORTARIA_LOTACAO');
            $total = (int) (clone $base)->count();
            $recent = (clone $base);
            if (Schema::hasColumn('DOCUMENTOS_SERVIDOR', 'created_at')) {
                $recent->where('created_at', '>=', now()->subDay());
            }

            $falhas = (int) (clone $recent)
                ->whereIn('STATUS_ENVIO_EMAIL', ['erro', 'falha'])
                ->count();
            $semEmail = (int) (clone $recent)
                ->where('STATUS_ENVIO_EMAIL', 'sem_email')
                ->count();
            $pendentes = (int) (clone $recent)
                ->where('STATUS_ENVIO_EMAIL', 'pendente')
                ->count();
            $enviados = (int) (clone $recent)
                ->where('STATUS_ENVIO_EMAIL', 'enviado')
                ->count();

            if ($falhas > 0) {
                return [
                    'ok' => false,
                    'message' => "Falha de entrega de portaria detectada: {$falhas} ocorrência(s) nas últimas 24h",
                    'recent_failures' => $falhas,
                    'without_email' => $semEmail,
                    'pending' => $pendentes,
                    'sent' => $enviados,
                    'total' => $total,
                    'impacted_modules' => ['Organograma', 'Dossiê Digital', 'Notificações'],
                ];
            }

            $warning = $semEmail > 0 || $pendentes > 0;
            return [
                'ok' => true,
                'warning' => $warning,
                'message' => $warning
                    ? "Entrega de portaria com pendências: {$semEmail} sem e-mail e {$pendentes} pendente(s)"
                    : 'Entrega de portarias operando normalmente',
                'without_email' => $semEmail,
                'pending' => $pendentes,
                'sent' => $enviados,
                'total' => $total,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => true,
                'warning' => true,
                'message' => 'Falha ao validar entrega de documentos: ' . $e->getMessage(),
            ];
        }
    }

    /** @return array<string,mixed> */
    private static function probeOrganogramIntegrity(): array
    {
        try {
            if (! self::tableExists('SETOR')) {
                return ['ok' => false, 'message' => 'Tabela SETOR ausente para validação do organograma'];
            }

            $q = DB::table('SETOR')->where('UNIDADE_ID', 0);
            if (Schema::hasColumn('SETOR', 'SETOR_ATIVO')) {
                $q->where('SETOR_ATIVO', 1);
            }
            $countZero = (int) (clone $q)->count();
            $hasCreatedAt = Schema::hasColumn('SETOR', 'created_at');
            $novosZero = $hasCreatedAt
                ? (int) (clone $q)->where('created_at', '>=', now()->subDay())->count()
                : 0;

            $ativosTotal = self::tableExists('FUNCIONARIO')
                ? (int) DB::table('FUNCIONARIO')
                    ->when(Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM'), fn ($q) => $q->whereNull('FUNCIONARIO_DATA_FIM'))
                    ->count()
                : 0;
            $lotadosAtivos = self::tableExists('LOTACAO')
                ? (int) DB::table('LOTACAO as l')
                    ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'l.FUNCIONARIO_ID')
                    ->when(Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM'), fn ($q) => $q->whereNull('l.LOTACAO_DATA_FIM'))
                    ->when(Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM'), fn ($q) => $q->whereNull('f.FUNCIONARIO_DATA_FIM'))
                    ->distinct()
                    ->count('f.FUNCIONARIO_ID')
                : 0;
            $limbo = max(0, $ativosTotal - $lotadosAtivos);
            $custoLimboMensal = 0.0;
            if (self::tableExists('CARGO') && Schema::hasColumn('FUNCIONARIO', 'CARGO_ID') && self::tableExists('LOTACAO')) {
                $custoLimboMensal = (float) DB::table('FUNCIONARIO as f')
                    ->leftJoin('LOTACAO as l', function ($j) {
                        $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID');
                        if (Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM')) {
                            $j->whereNull('l.LOTACAO_DATA_FIM');
                        }
                    })
                    ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
                    ->when(Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM'), fn ($q) => $q->whereNull('f.FUNCIONARIO_DATA_FIM'))
                    ->whereNull('l.LOTACAO_ID')
                    ->sum(DB::raw('COALESCE(c.CARGO_SALARIO, 0)'));
            }
            $lotacaoAtivaDuplicada = 0;
            $fechamentoInvalido = 0;
            if (self::tableExists('LOTACAO') && Schema::hasColumn('LOTACAO', 'FUNCIONARIO_ID') && Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM')) {
                $lotacaoAtivaDuplicada = (int) DB::table('LOTACAO')
                    ->select('FUNCIONARIO_ID')
                    ->whereNull('LOTACAO_DATA_FIM')
                    ->groupBy('FUNCIONARIO_ID')
                    ->havingRaw('COUNT(*) > 1')
                    ->count();
                if (Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_INICIO')) {
                    $fechamentoInvalido = (int) DB::table('LOTACAO')
                        ->whereNotNull('LOTACAO_DATA_FIM')
                        ->whereColumn('LOTACAO_DATA_FIM', '<', 'LOTACAO_DATA_INICIO')
                        ->count();
                }
            }
            if ($lotacaoAtivaDuplicada > 0 || $fechamentoInvalido > 0) {
                return [
                    'ok' => false,
                    'message' => 'Inconsistência de lotação detectada: vínculo ativo duplicado ou encerramento inválido',
                    'duplicidade_lotacao_ativa' => $lotacaoAtivaDuplicada,
                    'encerramento_invalido' => $fechamentoInvalido,
                    'servidores_em_limbo' => $limbo,
                    'custo_limbo_mensal_estimado' => round($custoLimboMensal, 2),
                    'impacted_modules' => ['Organograma', 'Gestão de Pessoas', 'Folha de Pagamento'],
                ];
            }

            if ($hasCreatedAt && $novosZero > 0) {
                return [
                    'ok' => false,
                    'message' => "Foram encontrados {$novosZero} novo(s) setor(es) com UNIDADE_ID=0 nas últimas 24h",
                    'impacted_modules' => ['Organograma', 'Gestão de Pessoas'],
                    'servidores_em_limbo' => $limbo,
                    'custo_limbo_mensal_estimado' => round($custoLimboMensal, 2),
                ];
            }

            if (! $hasCreatedAt && $countZero > 0) {
                return [
                    'ok' => true,
                    'warning' => true,
                    'message' => "Existem {$countZero} setor(es) legados com UNIDADE_ID=0 (sem rastreio temporal)",
                    'servidores_em_limbo' => $limbo,
                    'custo_limbo_mensal_estimado' => round($custoLimboMensal, 2),
                ];
            }

            return [
                'ok' => true,
                'message' => 'Organograma sem setores órfãos (UNIDADE_ID=0)',
                'servidores_em_limbo' => $limbo,
                'custo_limbo_mensal_estimado' => round($custoLimboMensal, 2),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Falha na validação do organograma: ' . $e->getMessage()];
        }
    }

    /** @return array<string,mixed> */
    private static function probeWorkforceCoherence(): array
    {
        try {
            if (! self::tableExists('FUNCIONARIO')) {
                return ['ok' => false, 'message' => 'Tabela FUNCIONARIO ausente para probe de coerência'];
            }
            if (! self::tableExists('LOTACAO')) {
                return ['ok' => false, 'message' => 'Tabela LOTACAO ausente para probe de coerência'];
            }

            $ativosTotal = (int) DB::table('FUNCIONARIO')
                ->when(Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM'), fn ($q) => $q->whereNull('FUNCIONARIO_DATA_FIM'))
                ->count();

            $lotadosAtivos = (int) DB::table('LOTACAO as l')
                ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'l.FUNCIONARIO_ID')
                ->when(Schema::hasColumn('SETOR', 'SETOR_ATIVO') && self::tableExists('SETOR'), function ($q) {
                    $q->join('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                        ->where('s.SETOR_ATIVO', 1);
                })
                ->when(Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM'), fn ($q) => $q->whereNull('l.LOTACAO_DATA_FIM'))
                ->when(Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM'), fn ($q) => $q->whereNull('f.FUNCIONARIO_DATA_FIM'))
                ->distinct()
                ->count('f.FUNCIONARIO_ID');

            $limbo = max(0, $ativosTotal - $lotadosAtivos);
            $limboPct = $ativosTotal > 0 ? round(($limbo / $ativosTotal) * 100, 2) : 0.0;
            $warn = $limbo >= 20 || $limboPct >= 5.0;
            $custoLimboMensal = 0.0;
            if (self::tableExists('CARGO') && Schema::hasColumn('FUNCIONARIO', 'CARGO_ID')) {
                $custoLimboMensal = (float) DB::table('FUNCIONARIO as f')
                    ->leftJoin('LOTACAO as l', function ($j) {
                        $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID');
                        if (Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM')) {
                            $j->whereNull('l.LOTACAO_DATA_FIM');
                        }
                    })
                    ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
                    ->when(Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM'), fn ($q) => $q->whereNull('f.FUNCIONARIO_DATA_FIM'))
                    ->whereNull('l.LOTACAO_ID')
                    ->sum(DB::raw('COALESCE(c.CARGO_SALARIO, 0)'));
            }

            return [
                'ok' => true,
                'warning' => $warn,
                'message' => $warn
                    ? "Coerência de força de trabalho com alerta: {$limbo} servidor(es) sem lotação ativa (custo estimado R$ " . number_format($custoLimboMensal, 2, ',', '.') . "/mês)"
                    : 'Coerência de força de trabalho dentro do esperado',
                'servidores_ativos_total' => $ativosTotal,
                'servidores_lotados_ativos' => $lotadosAtivos,
                'servidores_em_limbo' => $limbo,
                'limbo_pct' => $limboPct,
                'custo_limbo_mensal_estimado' => round($custoLimboMensal, 2),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Falha probe coerência força de trabalho: ' . $e->getMessage()];
        }
    }

    private static function firstExistingColumn(string $table, array $candidates): ?string
    {
        foreach ($candidates as $col) {
            try {
                if (Schema::hasColumn($table, $col)) {
                    return $col;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    private static function tableExists(string $table): bool
    {
        try {
            if (Schema::hasTable($table)) {
                return true;
            }
        } catch (\Throwable $e) {
            // fallback abaixo
        }

        // Fallback para SQL Server/collation/schema: valida com query leve.
        try {
            DB::table($table)->limit(1)->get();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param  list<string>  $missing
     * @return list<string>
     */
    private static function impactedModulesForMissingTables(array $missing): array
    {
        $map = [
            'PROGRESSAO' => ['Minha Progressão', 'Portal do Gestor', 'Folha de Pagamento'],
            'USUARIO' => ['Login', 'Dashboard', 'Permissões'],
            'FUNCIONARIO' => ['Portal do Gestor', 'Funcionários', 'Frequência'],
            'VINCULO' => ['Contratos e Vínculos', 'Folha de Pagamento'],
            'FOLHA' => ['Folha de Pagamento', 'Meus Holerites', 'Financeiro'],
        ];

        $mods = [];
        foreach ($missing as $t) {
            foreach (($map[$t] ?? []) as $m) {
                $mods[] = $m;
            }
        }

        return array_values(array_unique($mods));
    }

    /** @return array<string, mixed> */
    private static function probeBusinessIntegrity(): array
    {
        $user = self::pickProbeUser();
        if (! $user) {
            return ['ok' => false, 'message' => 'Sem usuário elegível para probe de negócio', 'impacted_modules' => ['Dashboard', 'Minha Progressão', 'Portal do Gestor']];
        }

        $targets = [
            [
                'uri' => '/api/v3/dashboard',
                'label' => 'Dashboard',
                'modules' => ['Dashboard', 'Folha de Pagamento'],
            ],
            [
                'uri' => '/api/v3/servidor/progressao',
                'label' => 'Minha Progressão',
                'modules' => ['Minha Progressão', 'Portal do Gestor', 'Folha de Pagamento'],
            ],
            [
                'uri' => '/api/v3/gestor',
                'label' => 'Portal do Gestor',
                'modules' => ['Portal do Gestor', 'Folha de Pagamento', 'Frequência'],
            ],
        ];

        $probeResults = [];
        $impacted = [];
        $okAll = true;

        foreach ($targets as $t) {
            $r = self::dispatchApiGetAsUser($t['uri'], $user);
            $status = (int) ($r['status'] ?? 500);
            $payload = $r['json'] ?? [];
            $isCont = self::isPayloadContingency($payload);

            $failed = $status >= 500 || $status === 0 || $isCont;
            if ($failed) {
                $okAll = false;
                foreach ($t['modules'] as $m) {
                    $impacted[] = $m;
                }
            }

            $probeResults[] = [
                'module' => $t['label'],
                'uri' => $t['uri'],
                'ok' => ! $failed,
                'status' => $status,
                'contingency_detected' => $isCont,
                'message' => $failed ? ($r['error'] ?? self::contingencyReason($payload) ?? "Falha {$status}") : 'Dados reais sem contingência',
            ];
        }

        return [
            'ok' => $okAll,
            'message' => $okAll ? 'Probes E2E de negócio sem contingência' : 'Contingência detectada em módulos críticos',
            'probes' => $probeResults,
            'impacted_modules' => array_values(array_unique($impacted)),
        ];
    }

    private static function pickProbeUser(): ?Usuario
    {
        $q = DB::table('USUARIO as u')
            ->join('FUNCIONARIO as f', 'f.USUARIO_ID', '=', 'u.USUARIO_ID')
            ->where('u.USUARIO_ATIVO', 1);
        if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM')) {
            $q->whereNull('f.FUNCIONARIO_DATA_FIM');
        }
        if (Schema::hasColumn('USUARIO', 'USUARIO_ALTERAR_SENHA')) {
            $q->where(function ($w) {
                $w->whereNull('u.USUARIO_ALTERAR_SENHA')->orWhere('u.USUARIO_ALTERAR_SENHA', 0);
            });
        }
        if (Schema::hasColumn('USUARIO', 'USUARIO_PRIMEIRO_ACESSO')) {
            $q->where(function ($w) {
                $w->whereNull('u.USUARIO_PRIMEIRO_ACESSO')->orWhere('u.USUARIO_PRIMEIRO_ACESSO', 0);
            });
        }
        $id = $q->orderBy('u.USUARIO_ID')->value('u.USUARIO_ID');
        if ($id) {
            return Usuario::query()->find((int) $id);
        }

        return Usuario::query()->where('USUARIO_ATIVO', 1)->orderBy('USUARIO_ID')->first();
    }

    /** @return array{status:int,json:array<string,mixed>,error?:string} */
    private static function dispatchApiGetAsUser(string $uri, Usuario $user): array
    {
        $prev = Auth::user();
        try {
            Auth::setUser($user);
            $req = Request::create($uri, 'GET', [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ]);
            $resp = app()->handle($req);
            $status = method_exists($resp, 'getStatusCode') ? (int) $resp->getStatusCode() : 500;
            $raw = method_exists($resp, 'getContent') ? (string) $resp->getContent() : '';
            $json = json_decode($raw, true);
            if (! is_array($json)) {
                $json = [];
            }

            return ['status' => $status, 'json' => $json];
        } catch (\Throwable $e) {
            return ['status' => 500, 'json' => [], 'error' => $e->getMessage()];
        } finally {
            if ($prev instanceof Usuario) {
                Auth::setUser($prev);
            } else {
                Auth::logout();
            }
        }
    }

    /** @param array<string,mixed> $payload */
    public static function isPayloadContingency(array $payload): bool
    {
        $flags = [
            'fallback',
            'is_contingency',
            'contingency',
            'safe_mode',
        ];
        foreach ($flags as $f) {
            if (array_key_exists($f, $payload) && $payload[$f] === true) {
                return true;
            }
        }

        $src = strtolower((string) ($payload['source'] ?? ''));
        if ($src === 'safe_mode' || $src === 'contingency') {
            return true;
        }

        $err = strtolower((string) ($payload['erro'] ?? $payload['error'] ?? ''));
        if ($err !== '' && (str_contains($err, 'invalid object name') || str_contains($err, 'conting') || str_contains($err, 'integra'))) {
            return true;
        }

        return false;
    }

    /** @param array<string,mixed> $payload */
    private static function contingencyReason(array $payload): ?string
    {
        if (! empty($payload['erro'])) {
            return (string) $payload['erro'];
        }
        if (! empty($payload['error'])) {
            return (string) $payload['error'];
        }
        if (($payload['fallback'] ?? false) === true) {
            return 'Payload marcado como fallback=true';
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    private static function safePersist(array $payload): void
    {
        try {
            Cache::put(self::CACHE_KEY, $payload, now()->addHours(24));
        } catch (\Throwable $e) {
            // Nunca derrubar a Sentinela por falha de persistência de cache.
        }
    }
}

