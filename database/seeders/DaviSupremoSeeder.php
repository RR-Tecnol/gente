<?php

namespace Database\Seeders;

use App\Support\GenteTenantType;
use App\Support\LoginLookupNormalizer;
use App\Support\RbacResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil fundador/supremo para homologação e bootstrap de ambientes limpos.
 *
 * **RBAC chapéu duplo (homolog):** com `GENTE_SEED_AUDITOR_SUPREMO=1`, após `RbacMatrixSeeder`, são criados dois
 * `GENTE_ASSIGNMENT` — `auditor_homologacao_ti` (GLOBAL_SEMED) e `auditoria_matriz_semad` (GLOBAL_SEMAD) — para
 * testar painel executivo + modo read-only SEMAD. Sem a variável, mantém-se `analista_executivo_sagep` em SEMED.
 *
 * IMPORTANTE: substitua os placeholders abaixo antes de produção:
 * - nome completo
 * - CPF
 * - e-mail/login
 * - senha inicial
 */
class DaviSupremoSeeder extends Seeder
{
    public function run(): void
    {
        $dados = [
            // Placeholder completo (não deixar nulo): troque para dados reais do fundador.
            'nome' => 'Davi Sobrenome',
            'cpf' => '00000000001',
            'email' => 'davi@semed.local',
            'matricula' => 'DEV-0001',
            'nascimento' => '1990-01-01',
            'admissao' => '2020-01-02',
            // Legado do projeto usa MD5 em USUARIO_SENHA (compatível com seeders atuais).
            'senha_plana' => 'Trocar@123',
        ];

        $login = LoginLookupNormalizer::forStorage($dados['email']);
        $cpfSomenteDigitos = preg_replace('/\D+/', '', $dados['cpf']);
        if (! is_string($cpfSomenteDigitos) || strlen($cpfSomenteDigitos) !== 11) {
            throw new \RuntimeException('CPF inválido no DaviSupremoSeeder (use 11 dígitos).');
        }

        $perfilDesenvolvedor = (int) (DB::table('PERFIL')->where('PERFIL_NOME', 'Desenvolvedor')->value('PERFIL_ID') ?? 0);
        $perfilAdministrador = (int) (DB::table('PERFIL')->where('PERFIL_NOME', 'Administrador')->value('PERFIL_ID') ?? 0);
        if ($perfilDesenvolvedor <= 0 || $perfilAdministrador <= 0) {
            throw new \RuntimeException('Perfis Desenvolvedor/Administrador ausentes. Rode PerfilSeeder antes.');
        }

        // Preferência operacional: SEMED -> SEMIT -> primeira unidade ativa.
        $unidadeId = (int) (DB::table('UNIDADE')->where('UNIDADE_SIGLA', 'SEMED')->value('UNIDADE_ID') ?? 0);
        if ($unidadeId <= 0) {
            $unidadeId = (int) (DB::table('UNIDADE')->where('UNIDADE_SIGLA', 'SEMIT')->value('UNIDADE_ID') ?? 0);
        }
        if ($unidadeId <= 0) {
            $qUn = DB::table('UNIDADE');
            if (Schema::hasColumn('UNIDADE', 'UNIDADE_ATIVA')) {
                $qUn->where('UNIDADE_ATIVA', 1);
            } elseif (Schema::hasColumn('UNIDADE', 'UNIDADE_ATIVO')) {
                $qUn->where('UNIDADE_ATIVO', 1);
            }
            $unidadeId = (int) ($qUn->orderBy('UNIDADE_ID')->value('UNIDADE_ID') ?? 0);
        }
        if ($unidadeId <= 0) {
            throw new \RuntimeException('Nenhuma UNIDADE disponível para lotação do perfil supremo.');
        }

        // Setor raiz/topo: prioriza gabinete, depois o primeiro setor da unidade.
        $setorId = (int) (DB::table('SETOR')
            ->where('UNIDADE_ID', $unidadeId)
            ->where('SETOR_NOME', 'like', '%Gabinete%')
            ->orderBy('SETOR_ID')
            ->value('SETOR_ID') ?? 0);
        if ($setorId <= 0) {
            $setorId = (int) (DB::table('SETOR')->where('UNIDADE_ID', $unidadeId)->orderBy('SETOR_ID')->value('SETOR_ID') ?? 0);
        }
        if ($setorId <= 0) {
            throw new \RuntimeException("Nenhum SETOR encontrado para UNIDADE_ID {$unidadeId}.");
        }

        $vinculoId = (int) (DB::table('VINCULO')->orderBy('VINCULO_ID')->value('VINCULO_ID') ?? 0) ?: null;
        $senhaMd5 = md5($dados['senha_plana']);
        $hoje = now()->toDateString();

        DB::transaction(function () use (
            $dados,
            $cpfSomenteDigitos,
            $login,
            $perfilDesenvolvedor,
            $perfilAdministrador,
            $unidadeId,
            $setorId,
            $vinculoId,
            $senhaMd5,
            $hoje
        ) {
            // 1) PESSOA (updateOrCreate pelo CPF)
            $pessoaCpfCol = Schema::hasColumn('PESSOA', 'PESSOA_CPF_NUMERO') ? 'PESSOA_CPF_NUMERO' : 'PESSOA_CPF';
            $pessoa = DB::table('PESSOA')->where($pessoaCpfCol, $cpfSomenteDigitos)->first();
            $pessoaData = [
                'PESSOA_NOME' => $dados['nome'],
            ];
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

            // 2) FUNCIONARIO (updateOrCreate por matrícula)
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

            // 3) USUARIO (updateOrCreate por login/email)
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
                $usuarioData['PERFIL_ID'] = $perfilAdministrador;
            }
            $usuarioId = $usuario ? (int) $usuario->USUARIO_ID : (int) DB::table('USUARIO')->insertGetId($usuarioData);
            if ($usuario) {
                DB::table('USUARIO')->where('USUARIO_ID', $usuarioId)->update($usuarioData);
            }

            // Referência cruzada para o fallback do /me.
            if (Schema::hasColumn('FUNCIONARIO', 'USUARIO_ID')) {
                DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $funcionarioId)->update(['USUARIO_ID' => $usuarioId]);
            }

            // 4) USUARIO_PERFIL (máximo acesso: Desenvolvedor + Administrador)
            DB::table('USUARIO_PERFIL')->updateOrInsert(
                ['USUARIO_ID' => $usuarioId, 'PERFIL_ID' => $perfilDesenvolvedor],
                ['USUARIO_PERFIL_ATIVO' => 1]
            );
            DB::table('USUARIO_PERFIL')->updateOrInsert(
                ['USUARIO_ID' => $usuarioId, 'PERFIL_ID' => $perfilAdministrador],
                ['USUARIO_PERFIL_ATIVO' => 1]
            );

            // 5) LOTACAO (garante lotação ativa no topo da unidade)
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

            // 6) Escopo de unidade e âncora de setor (funcionário real + acesso máximo no tenant model)
            if (Schema::hasTable('USUARIO_UNIDADE')) {
                $scopeVals = [];
                if (Schema::hasColumn('USUARIO_UNIDADE', 'USUARIO_UNIDADE_ATIVO')) {
                    $scopeVals['USUARIO_UNIDADE_ATIVO'] = 1;
                }
                if (Schema::hasColumn('USUARIO_UNIDADE', 'USUARIO_UNIDADE_FISCAL')) {
                    $scopeVals['USUARIO_UNIDADE_FISCAL'] = 1;
                }
                if ($scopeVals === []) {
                    $scopeVals = ['USUARIO_UNIDADE_ATIVO' => 1];
                }
                DB::table('USUARIO_UNIDADE')->updateOrInsert(
                    ['USUARIO_ID' => $usuarioId, 'UNIDADE_ID' => $unidadeId],
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

            // 7) RBAC (GENTE_ASSIGNMENT): chapéu duplo homologação (opt-in) ou papel SAGEP legado (padrão)
            if (Schema::hasTable('GENTE_ASSIGNMENT') && Schema::hasTable('GENTE_ROLE')) {
                $nowTs = now();
                $auditorSupremo = filter_var(env('GENTE_SEED_AUDITOR_SUPREMO', false), FILTER_VALIDATE_BOOLEAN);

                if ($auditorSupremo) {
                    $anchorSemedId = RbacResolver::resolveGlobalSemedUnidadeId();
                    if ($anchorSemedId === null || $anchorSemedId <= 0) {
                        throw new \RuntimeException(
                            'Âncora RBAC GLOBAL_SEMED não encontrada (UNIDADE: "'.config('gente.rbac.anchor_unidade_nome_global_semed').'"). Verifique migration 100450 e GENTE_RBAC_ANCORA_SEMED_NOME.'
                        );
                    }
                    $roleTiId = (int) (DB::table('GENTE_ROLE')->where('ROLE_SLUG', 'auditor_homologacao_ti')->value('GENTE_ROLE_ID') ?? 0);
                    if ($roleTiId <= 0) {
                        throw new \RuntimeException('GENTE_ROLE auditor_homologacao_ti ausente. Rode RbacMatrixSeeder antes de DaviSupremoSeeder.');
                    }
                    DB::table('GENTE_ASSIGNMENT')->updateOrInsert(
                        [
                            'USUARIO_ID' => $usuarioId,
                            'GENTE_ROLE_ID' => $roleTiId,
                            'TENANT_TYPE' => GenteTenantType::GLOBAL_SEMED,
                            'TENANT_ID' => $anchorSemedId,
                        ],
                        [
                            'VIGENCIA_INICIO' => '2020-01-01',
                            'VIGENCIA_FIM' => null,
                            'ASSIGNMENT_ATIVO' => 1,
                            'ORIGEM' => 'seed_auditor_homologacao_ti',
                            'METADADOS_JSON' => null,
                            'updated_at' => $nowTs,
                            'created_at' => $nowTs,
                        ]
                    );

                    $anchorSemadId = RbacResolver::resolveGlobalSemadUnidadeId();
                    if ($anchorSemadId === null || $anchorSemadId <= 0) {
                        throw new \RuntimeException(
                            'Âncora RBAC GLOBAL_SEMAD não encontrada (UNIDADE: "'.config('gente.rbac.anchor_unidade_nome_global_semad').'"). Defina GENTE_RBAC_ANCORA_SEMAD_NOME ou crie a unidade âncora.'
                        );
                    }
                    $roleSemadId = (int) (DB::table('GENTE_ROLE')->where('ROLE_SLUG', 'auditoria_matriz_semad')->value('GENTE_ROLE_ID') ?? 0);
                    if ($roleSemadId <= 0) {
                        throw new \RuntimeException('GENTE_ROLE auditoria_matriz_semad ausente. Rode RbacMatrixSeeder antes de DaviSupremoSeeder.');
                    }
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
                            'ORIGEM' => 'seed_auditoria_matriz_semad',
                            'METADADOS_JSON' => null,
                            'updated_at' => $nowTs,
                            'created_at' => $nowTs,
                        ]
                    );
                } else {
                    $anchorSemedId = RbacResolver::resolveGlobalSemedUnidadeId();
                    if ($anchorSemedId !== null && $anchorSemedId > 0) {
                        $roleSagepId = (int) (DB::table('GENTE_ROLE')->where('ROLE_SLUG', 'analista_executivo_sagep')->value('GENTE_ROLE_ID') ?? 0);
                        if ($roleSagepId > 0) {
                            DB::table('GENTE_ASSIGNMENT')->updateOrInsert(
                                [
                                    'USUARIO_ID' => $usuarioId,
                                    'GENTE_ROLE_ID' => $roleSagepId,
                                    'TENANT_TYPE' => GenteTenantType::GLOBAL_SEMED,
                                    'TENANT_ID' => $anchorSemedId,
                                ],
                                [
                                    'VIGENCIA_INICIO' => '2020-01-01',
                                    'VIGENCIA_FIM' => null,
                                    'ASSIGNMENT_ATIVO' => 1,
                                    'ORIGEM' => 'seed_fundador',
                                    'METADADOS_JSON' => null,
                                    'updated_at' => $nowTs,
                                    'created_at' => $nowTs,
                                ]
                            );
                        } else {
                            throw new \RuntimeException('GENTE_ROLE analista_executivo_sagep ausente. Rode RbacMatrixSeeder antes de DaviSupremoSeeder.');
                        }
                    } else {
                        throw new \RuntimeException(
                            'Âncora RBAC GLOBAL_SEMED não encontrada (UNIDADE: "'.config('gente.rbac.anchor_unidade_nome_global_semed').'"). Verifique migration 100450 e GENTE_RBAC_ANCORA_SEMED_NOME.'
                        );
                    }
                }
            }
        });

        $this->command?->info("✅ DaviSupremoSeeder: usuário {$login} pronto (matrícula {$dados['matricula']}).");
        $this->command?->warn('⚠ Adicione o e-mail no .env para Sudo Mode: GENTE_SUPER_ADMIN_EMAILS="davi@semed.local"');
    }
}

