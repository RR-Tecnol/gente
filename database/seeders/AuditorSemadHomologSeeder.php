<?php

namespace Database\Seeders;

use App\Support\GenteTenantType;
use App\Support\LoginLookupNormalizer;
use App\Support\RbacResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Homologação / Go-Live: utilizador com **um único** assignment RBAC — papel `auditoria_matriz_semad` em `GLOBAL_SEMAD`.
 *
 * Opt-in: `GENTE_SEED_AUDITOR_SEMAD_STANDALONE=1` (ver `DatabaseSeeder`). Exige `RbacMatrixSeeder` já executado
 * (papel e âncora SEMAD presentes). Ver `docs/davi/RBAC_ZERO_TRUST_BACKLOG.md` para a refacção futura de isolamento por sessão.
 */
class AuditorSemadHomologSeeder extends Seeder
{
    public function run(): void
    {
        if (! filter_var(env('GENTE_SEED_AUDITOR_SEMAD_STANDALONE', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (! Schema::hasTable('GENTE_ASSIGNMENT') || ! Schema::hasTable('GENTE_ROLE')) {
            $this->command?->warn('AuditorSemadHomologSeeder: tabelas RBAC ausentes — ignorado.');

            return;
        }

        $dados = [
            'nome' => 'Auditor SEMAD (homologação isolada)',
            'cpf' => '00000000002',
            'email' => 'auditor@semad.local',
            'matricula' => 'AUD-SEMAD-HOMOLOG',
            'nascimento' => '1990-01-01',
            'admissao' => '2020-01-02',
            'senha_plana' => 'Trocar@123',
        ];

        $login = LoginLookupNormalizer::forStorage($dados['email']);
        $cpfSomenteDigitos = preg_replace('/\D+/', '', $dados['cpf']);
        if (! is_string($cpfSomenteDigitos) || strlen($cpfSomenteDigitos) !== 11) {
            throw new \RuntimeException('CPF inválido no AuditorSemadHomologSeeder (use 11 dígitos).');
        }

        $perfilGestaoId = (int) (DB::table('PERFIL')->where('PERFIL_NOME', 'Gestão')->value('PERFIL_ID') ?? 0);
        if ($perfilGestaoId <= 0) {
            throw new \RuntimeException('Perfil Gestão ausente. Rode PerfilSeeder antes.');
        }

        $anchorSemadId = RbacResolver::resolveGlobalSemadUnidadeId();
        if ($anchorSemadId === null || $anchorSemadId <= 0) {
            throw new \RuntimeException(
                'Âncora RBAC GLOBAL_SEMAD não encontrada (UNIDADE: "'.config('gente.rbac.anchor_unidade_nome_global_semad').'").'
            );
        }

        $setorId = (int) (DB::table('SETOR')
            ->where('UNIDADE_ID', $anchorSemadId)
            ->orderBy('SETOR_ID')
            ->value('SETOR_ID') ?? 0);
        // A âncora GLOBAL_SEMAD pode existir só para RBAC (sem organograma); garantimos um setor mínimo para lotação.
        if ($setorId <= 0) {
            $setorRow = [
                'SETOR_NOME' => 'Gabinete âncora SEMAD (homolog seed)',
                'UNIDADE_ID' => $anchorSemadId,
            ];
            if (Schema::hasColumn('SETOR', 'SETOR_ATIVO')) {
                $setorRow['SETOR_ATIVO'] = 1;
            }
            if (Schema::hasColumn('SETOR', 'SETOR_SIGLA')) {
                $setorRow['SETOR_SIGLA'] = 'SEMAD-RBAC';
            }
            $setorId = (int) DB::table('SETOR')->insertGetId($setorRow);
            if ($setorId <= 0) {
                throw new \RuntimeException("Não foi possível criar SETOR para a âncora SEMAD (UNIDADE_ID {$anchorSemadId}).");
            }
            $this->command?->warn("AuditorSemadHomologSeeder: criado SETOR_ID {$setorId} na âncora SEMAD (não havia organograma).");
        }

        $vinculoId = (int) (DB::table('VINCULO')->orderBy('VINCULO_ID')->value('VINCULO_ID') ?? 0) ?: null;
        $senhaMd5 = md5($dados['senha_plana']);
        $hoje = now()->toDateString();

        DB::transaction(function () use (
            $dados,
            $cpfSomenteDigitos,
            $login,
            $perfilGestaoId,
            $anchorSemadId,
            $setorId,
            $vinculoId,
            $senhaMd5,
            $hoje
        ) {
            $pessoaCpfCol = Schema::hasColumn('PESSOA', 'PESSOA_CPF_NUMERO') ? 'PESSOA_CPF_NUMERO' : 'PESSOA_CPF';
            $pessoa = DB::table('PESSOA')->where($pessoaCpfCol, $cpfSomenteDigitos)->first();
            $pessoaData = ['PESSOA_NOME' => $dados['nome']];
            if (Schema::hasColumn('PESSOA', 'PESSOA_CPF_NUMERO')) {
                $pessoaData['PESSOA_CPF_NUMERO'] = $cpfSomenteDigitos;
            }
            if (Schema::hasColumn('PESSOA', 'PESSOA_CPF')) {
                $pessoaData['PESSOA_CPF'] = $cpfSomenteDigitos;
            }
            if (Schema::hasColumn('PESSOA', 'PESSOA_DATA_NASCIMENTO')) {
                $pessoaData['PESSOA_DATA_NASCIMENTO'] = $dados['nascimento'];
            } elseif (Schema::hasColumn('PESSOA', 'PESSOA_NASC')) {
                $pessoaData['PESSOA_NASC'] = $dados['nascimento'];
            }
            if (Schema::hasColumn('PESSOA', 'PESSOA_SEXO')) {
                $pessoaData['PESSOA_SEXO'] = 1;
            }
            if (Schema::hasColumn('PESSOA', 'PESSOA_ATIVO')) {
                $pessoaData['PESSOA_ATIVO'] = 1;
            }
            if (Schema::hasColumn('PESSOA', 'PESSOA_DATA_CADASTRO')) {
                $pessoaData['PESSOA_DATA_CADASTRO'] = $hoje;
            }
            $pessoaId = $pessoa ? (int) $pessoa->PESSOA_ID : (int) DB::table('PESSOA')->insertGetId($pessoaData);
            if ($pessoa) {
                DB::table('PESSOA')->where('PESSOA_ID', $pessoaId)->update($pessoaData);
            }

            $func = DB::table('FUNCIONARIO')->where('FUNCIONARIO_MATRICULA', $dados['matricula'])->first();
            $funcData = [
                'PESSOA_ID' => $pessoaId,
                'FUNCIONARIO_MATRICULA' => $dados['matricula'],
            ];
            if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_INICIO')) {
                $funcData['FUNCIONARIO_DATA_INICIO'] = $dados['admissao'];
            }
            if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_ATIVO')) {
                $funcData['FUNCIONARIO_ATIVO'] = 1;
            }
            if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM')) {
                $funcData['FUNCIONARIO_DATA_FIM'] = null;
            }
            if (Schema::hasColumn('FUNCIONARIO', 'VINCULO_ID') && $vinculoId) {
                $funcData['VINCULO_ID'] = $vinculoId;
            }
            if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_REGIME_PREV')) {
                $funcData['FUNCIONARIO_REGIME_PREV'] = 'RPPS';
            }
            if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_CADASTRO')) {
                $funcData['FUNCIONARIO_DATA_CADASTRO'] = $hoje;
            }
            if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_ATUALIZACAO')) {
                $funcData['FUNCIONARIO_DATA_ATUALIZACAO'] = $hoje;
            }
            $funcionarioId = $func ? (int) $func->FUNCIONARIO_ID : (int) DB::table('FUNCIONARIO')->insertGetId($funcData);
            if ($func) {
                DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $funcionarioId)->update($funcData);
            }

            $usuario = DB::table('USUARIO')->where('USUARIO_LOGIN', $login)->first();
            $usuarioData = [
                'USUARIO_LOGIN' => $login,
                'USUARIO_NOME' => $dados['nome'],
                'USUARIO_SENHA' => $senhaMd5,
                'USUARIO_ATIVO' => 1,
            ];
            if (Schema::hasColumn('USUARIO', 'USUARIO_EMAIL')) {
                $usuarioData['USUARIO_EMAIL'] = $login;
            }
            if (Schema::hasColumn('USUARIO', 'USUARIO_CPF')) {
                $usuarioData['USUARIO_CPF'] = $cpfSomenteDigitos;
            }
            if (Schema::hasColumn('USUARIO', 'USUARIO_PRIMEIRO_ACESSO')) {
                $usuarioData['USUARIO_PRIMEIRO_ACESSO'] = 1;
            }
            if (Schema::hasColumn('USUARIO', 'USUARIO_ALTERAR_SENHA')) {
                $usuarioData['USUARIO_ALTERAR_SENHA'] = 0;
            }
            if (Schema::hasColumn('USUARIO', 'USUARIO_VIGENCIA')) {
                $usuarioData['USUARIO_VIGENCIA'] = $hoje;
            }
            if (Schema::hasColumn('USUARIO', 'FUNCIONARIO_ID')) {
                $usuarioData['FUNCIONARIO_ID'] = $funcionarioId;
            }
            if (Schema::hasColumn('USUARIO', 'PERFIL_ID')) {
                $usuarioData['PERFIL_ID'] = $perfilGestaoId;
            }
            $usuarioId = $usuario ? (int) $usuario->USUARIO_ID : (int) DB::table('USUARIO')->insertGetId($usuarioData);
            if ($usuario) {
                DB::table('USUARIO')->where('USUARIO_ID', $usuarioId)->update($usuarioData);
            }

            if (Schema::hasColumn('FUNCIONARIO', 'USUARIO_ID')) {
                DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $funcionarioId)->update(['USUARIO_ID' => $usuarioId]);
            }

            DB::table('USUARIO_PERFIL')->updateOrInsert(
                ['USUARIO_ID' => $usuarioId, 'PERFIL_ID' => $perfilGestaoId],
                ['USUARIO_PERFIL_ATIVO' => 1]
            );

            if (Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM')) {
                DB::table('LOTACAO')
                    ->where('FUNCIONARIO_ID', $funcionarioId)
                    ->whereNull('LOTACAO_DATA_FIM')
                    ->update(['LOTACAO_DATA_FIM' => $hoje]);
            }
            $lotData = [
                'FUNCIONARIO_ID' => $funcionarioId,
                'SETOR_ID' => $setorId,
            ];
            if (Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_INICIO')) {
                $lotData['LOTACAO_DATA_INICIO'] = $dados['admissao'];
            }
            if (Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM')) {
                $lotData['LOTACAO_DATA_FIM'] = null;
            }
            if (Schema::hasColumn('LOTACAO', 'VINCULO_ID') && $vinculoId) {
                $lotData['VINCULO_ID'] = $vinculoId;
            }
            DB::table('LOTACAO')->insert($lotData);

            if (Schema::hasTable('USUARIO_UNIDADE')) {
                $scopeVals = [];
                if (Schema::hasColumn('USUARIO_UNIDADE', 'USUARIO_UNIDADE_ATIVO')) {
                    $scopeVals['USUARIO_UNIDADE_ATIVO'] = 1;
                }
                if (Schema::hasColumn('USUARIO_UNIDADE', 'USUARIO_UNIDADE_FISCAL')) {
                    $scopeVals['USUARIO_UNIDADE_FISCAL'] = 0;
                }
                if ($scopeVals === []) {
                    $scopeVals = ['USUARIO_UNIDADE_ATIVO' => 1];
                }
                DB::table('USUARIO_UNIDADE')->updateOrInsert(
                    ['USUARIO_ID' => $usuarioId, 'UNIDADE_ID' => $anchorSemadId],
                    $scopeVals
                );
            }
            if (Schema::hasTable('USUARIO_SETOR')) {
                $setorVals = [];
                if (Schema::hasColumn('USUARIO_SETOR', 'USUARIO_SETOR_ATIVO')) {
                    $setorVals['USUARIO_SETOR_ATIVO'] = 1;
                } elseif (Schema::hasColumn('USUARIO_SETOR', 'ATIVO')) {
                    $setorVals['ATIVO'] = 1;
                } else {
                    $setorVals = ['USUARIO_SETOR_ATIVO' => 1];
                }
                DB::table('USUARIO_SETOR')->updateOrInsert(
                    ['USUARIO_ID' => $usuarioId, 'SETOR_ID' => $setorId],
                    $setorVals
                );
            }

            $roleSemadId = (int) (DB::table('GENTE_ROLE')->where('ROLE_SLUG', 'auditoria_matriz_semad')->value('GENTE_ROLE_ID') ?? 0);
            if ($roleSemadId <= 0) {
                throw new \RuntimeException('GENTE_ROLE auditoria_matriz_semad ausente. Rode RbacMatrixSeeder antes de AuditorSemadHomologSeeder.');
            }

            $nowTs = now();
            DB::table('GENTE_ASSIGNMENT')->updateOrInsert(
                [
                    'USUARIO_ID' => $usuarioId,
                    'GENTE_ROLE_ID' => $roleSemadId,
                    'TENANT_TYPE' => GenteTenantType::GLOBAL_SEMAD,
                    'TENANT_ID' => $anchorSemadId,
                ],
                [
                    'VIGENCIA_INICIO' => '2020-01-01',
                    'VIGENCIA_FIM' => null,
                    'ASSIGNMENT_ATIVO' => 1,
                    'ORIGEM' => 'seed_auditor_sem_standalone',
                    'METADADOS_JSON' => null,
                    'updated_at' => $nowTs,
                    'created_at' => $nowTs,
                ]
            );
        });

        $this->command?->info("✅ AuditorSemadHomologSeeder: usuário {$login} (matrícula {$dados['matricula']}) — um assignment SEMAD apenas.");
    }
}
