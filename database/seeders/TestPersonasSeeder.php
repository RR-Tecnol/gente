<?php

namespace Database\Seeders;

use App\Support\LoginLookupNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Laboratório SEMAD — 4 personas fixas para homologação (São Luís).
 * Senha: gente@2026 (armazenada como MD5, padrão legado USUARIO).
 *
 * Requer: tabelas PERFIL, UNIDADE, SETOR, VINCULO (rode PerfilSeeder + OrganogramaPMSLzSeeder antes).
 *
 * php artisan db:seed --class=TestPersonasSeeder
 */
class TestPersonasSeeder extends Seeder
{
    public function run(): void
    {
        $senhaMd5 = md5('gente@2026');

        $perfilId = function (string $nome): int {
            $id = DB::table('PERFIL')->where('PERFIL_NOME', $nome)->value('PERFIL_ID');
            if (!$id) {
                throw new \RuntimeException("PERFIL não encontrado: \"{$nome}\". Execute: php artisan db:seed --class=PerfilSeeder");
            }

            return (int) $id;
        };

        $qUnidade = DB::table('UNIDADE');
        if (Schema::hasColumn('UNIDADE', 'UNIDADE_ATIVA')) {
            $qUnidade->where('UNIDADE_ATIVA', 1);
        } elseif (Schema::hasColumn('UNIDADE', 'UNIDADE_ATIVO')) {
            $qUnidade->where('UNIDADE_ATIVO', 1);
        }
        $primeiraUnidade = $qUnidade->orderBy('UNIDADE_ID')->first();
        if (!$primeiraUnidade) {
            throw new \RuntimeException('Nenhuma UNIDADE ativa. Execute OrganogramaPMSLzSeeder ou cadastre unidades.');
        }
        $unidadePrimeira = (int) $primeiraUnidade->UNIDADE_ID;

        $setorDaUnidade = function (int $unidadeId): int {
            $sid = DB::table('SETOR')
                ->where('UNIDADE_ID', $unidadeId)
                ->orderBy('SETOR_ID')
                ->value('SETOR_ID');
            if (!$sid) {
                throw new \RuntimeException("Nenhum SETOR para UNIDADE_ID {$unidadeId}.");
            }

            return (int) $sid;
        };

        $vinculoId = DB::table('VINCULO')->orderBy('VINCULO_ID')->value('VINCULO_ID');

        $semadUnidadeId = DB::table('UNIDADE')->where('UNIDADE_SIGLA', 'SEMAD')->value('UNIDADE_ID');
        $semfazUnidadeId = DB::table('UNIDADE')->where('UNIDADE_SIGLA', 'SEMFAZ')->value('UNIDADE_ID');
        $lotTi = $semadUnidadeId ? (int) $semadUnidadeId : $unidadePrimeira;
        $lotRh = $semadUnidadeId ? (int) $semadUnidadeId : $unidadePrimeira;
        $lotFolha = $semfazUnidadeId ? (int) $semfazUnidadeId : $unidadePrimeira;

        $personas = [
            [
                'login' => 'ti@saoluis.ma.gov.br',
                'nome' => 'Lab SEMAD — TI (Super Admin)',
                'cpf' => '91827364005',
                'matricula' => 'LAB-2026-TI',
                'perfil_nomes' => ['Desenvolvedor', 'Administrador'],
                'unidade_lotacao_id' => $lotTi,
                'vincular_usuario_unidade' => false,
                'unidade_usuario_id' => null,
            ],
            [
                'login' => 'rh@saoluis.ma.gov.br',
                'nome' => 'Lab SEMAD — RH Central',
                'cpf' => '82736459014',
                'matricula' => 'LAB-2026-RH',
                'perfil_nomes' => ['RH Folha'],
                'unidade_lotacao_id' => $lotRh,
                'vincular_usuario_unidade' => false,
                'unidade_usuario_id' => null,
            ],
            [
                'login' => 'folha@saoluis.ma.gov.br',
                'nome' => 'Lab SEMAD — Gestão de Folha (Financeiro)',
                'cpf' => '73625148023',
                'matricula' => 'LAB-2026-FL',
                'perfil_nomes' => ['Gestão'],
                'unidade_lotacao_id' => $lotFolha,
                'vincular_usuario_unidade' => false,
                'unidade_usuario_id' => null,
            ],
            [
                'login' => 'gestor.escola@saoluis.ma.gov.br',
                'nome' => 'Lab SEMAD — Diretor de Unidade',
                'cpf' => '62514037032',
                'matricula' => 'LAB-2026-GE',
                'perfil_nomes' => ['Diretor / Gestor de Unidade'],
                'unidade_lotacao_id' => $unidadePrimeira,
                'vincular_usuario_unidade' => true,
                'unidade_usuario_id' => $unidadePrimeira,
            ],
        ];

        DB::transaction(function () use ($personas, $senhaMd5, $perfilId, $setorDaUnidade, $vinculoId) {
            foreach ($personas as $p) {
                $loginCanon = LoginLookupNormalizer::forStorage((string) $p['login']);
                $perfilIds = array_map($perfilId, $p['perfil_nomes']);
                $setorId = $setorDaUnidade($p['unidade_lotacao_id']);

                $pessoaId = DB::table('PESSOA')->where('PESSOA_CPF_NUMERO', $p['cpf'])->value('PESSOA_ID');
                if (!$pessoaId) {
                    $pessoaData = [
                        'PESSOA_NOME' => $p['nome'],
                        'PESSOA_CPF_NUMERO' => $p['cpf'],
                        'PESSOA_SEXO' => 1,
                        'PESSOA_DATA_NASCIMENTO' => '1985-01-15',
                    ];
                    if (Schema::hasColumn('PESSOA', 'PESSOA_ATIVO')) {
                        $pessoaData['PESSOA_ATIVO'] = 1;
                    }
                    if (Schema::hasColumn('PESSOA', 'PESSOA_CPF')) {
                        $pessoaData['PESSOA_CPF'] = $p['cpf'];
                    }
                    if (Schema::hasColumn('PESSOA', 'PESSOA_DATA_CADASTRO')) {
                        $pessoaData['PESSOA_DATA_CADASTRO'] = now()->toDateString();
                    }
                    $pessoaId = DB::table('PESSOA')->insertGetId($pessoaData);
                }

                $funcId = DB::table('FUNCIONARIO')->where('FUNCIONARIO_MATRICULA', $p['matricula'])->value('FUNCIONARIO_ID');
                if (!$funcId) {
                    $funcData = [
                        'PESSOA_ID' => $pessoaId,
                        'FUNCIONARIO_MATRICULA' => $p['matricula'],
                        'FUNCIONARIO_DATA_INICIO' => '2020-01-01',
                    ];
                    if (Schema::hasColumn('FUNCIONARIO', 'VINCULO_ID') && $vinculoId) {
                        $funcData['VINCULO_ID'] = $vinculoId;
                    }
                    if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_ATIVO')) {
                        $funcData['FUNCIONARIO_ATIVO'] = 1;
                    }
                    if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_CADASTRO')) {
                        $funcData['FUNCIONARIO_DATA_CADASTRO'] = now()->toDateString();
                    }
                    $funcId = DB::table('FUNCIONARIO')->insertGetId($funcData);
                }

                $lotExiste = DB::table('LOTACAO')
                    ->where('FUNCIONARIO_ID', $funcId)
                    ->whereNull('LOTACAO_DATA_FIM')
                    ->exists();
                if (!$lotExiste) {
                    $lot = [
                        'FUNCIONARIO_ID' => $funcId,
                        'SETOR_ID' => $setorId,
                    ];
                    if (Schema::hasColumn('LOTACAO', 'VINCULO_ID') && $vinculoId) {
                        $lot['VINCULO_ID'] = $vinculoId;
                    }
                    if (Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_INICIO')) {
                        $lot['LOTACAO_DATA_INICIO'] = '2020-01-02';
                    }
                    DB::table('LOTACAO')->insert($lot);
                }

                $usuarioRow = DB::table('USUARIO')->where('USUARIO_LOGIN', $loginCanon)->first();
                if (!$usuarioRow) {
                    $insertU = [
                        'USUARIO_LOGIN' => $loginCanon,
                        'USUARIO_SENHA' => $senhaMd5,
                        'USUARIO_NOME' => $p['nome'],
                        'USUARIO_ATIVO' => 1,
                    ];
                    if (Schema::hasColumn('USUARIO', 'USUARIO_PRIMEIRO_ACESSO')) {
                        $insertU['USUARIO_PRIMEIRO_ACESSO'] = 1;
                    }
                    if (Schema::hasColumn('USUARIO', 'USUARIO_ALTERAR_SENHA')) {
                        $insertU['USUARIO_ALTERAR_SENHA'] = 0;
                    }
                    $usuarioId = DB::table('USUARIO')->insertGetId($insertU);
                } else {
                    $usuarioId = (int) $usuarioRow->USUARIO_ID;
                    $upU = [
                        'USUARIO_SENHA' => $senhaMd5,
                        'USUARIO_NOME' => $p['nome'],
                        'USUARIO_ATIVO' => 1,
                    ];
                    if (Schema::hasColumn('USUARIO', 'USUARIO_PRIMEIRO_ACESSO')) {
                        $upU['USUARIO_PRIMEIRO_ACESSO'] = 1;
                    }
                    if (Schema::hasColumn('USUARIO', 'USUARIO_ALTERAR_SENHA')) {
                        $upU['USUARIO_ALTERAR_SENHA'] = 0;
                    }
                    DB::table('USUARIO')
                        ->where('USUARIO_ID', $usuarioId)
                        ->update($upU);
                }

                if (Schema::hasColumn('USUARIO', 'USUARIO_EMAIL')) {
                    DB::table('USUARIO')
                        ->where('USUARIO_ID', $usuarioId)
                        ->update(['USUARIO_EMAIL' => $loginCanon]);
                }
                if (Schema::hasColumn('USUARIO', 'FUNCIONARIO_ID')) {
                    DB::table('USUARIO')
                        ->where('USUARIO_ID', $usuarioId)
                        ->update(['FUNCIONARIO_ID' => $funcId]);
                }

                DB::table('FUNCIONARIO')
                    ->where('FUNCIONARIO_ID', $funcId)
                    ->update(['USUARIO_ID' => $usuarioId]);

                DB::table('USUARIO_PERFIL')->where('USUARIO_ID', $usuarioId)->delete();
                foreach ($perfilIds as $pid) {
                    DB::table('USUARIO_PERFIL')->updateOrInsert(
                        ['USUARIO_ID' => $usuarioId, 'PERFIL_ID' => $pid],
                        ['USUARIO_PERFIL_ATIVO' => 1]
                    );
                }

                if (!empty($p['vincular_usuario_unidade']) && (int) $p['unidade_usuario_id'] > 0) {
                    $uidScope = (int) $p['unidade_usuario_id'];
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
                        ['USUARIO_ID' => $usuarioId, 'UNIDADE_ID' => $uidScope],
                        $scopeVals
                    );
                }

                $perfilResumo = implode(' + ', $p['perfil_nomes']);
                $this->command->line("  ✓ [{$loginCanon}] {$p['nome']} — perfis: {$perfilResumo} — matrícula {$p['matricula']}");
            }
        });

        $uEsc = $personas[3];
        $this->command->info('✅ TestPersonasSeeder: 4 personas Lab SEMAD prontas.');
        $nomUn = $primeiraUnidade->UNIDADE_NOME ?? '—';
        $this->command->line("   → gestor.escola: USUARIO_UNIDADE → UNIDADE_ID {$uEsc['unidade_usuario_id']} ({$nomUn})");
        $this->command->warn('   Senha de todas: gente@2026 (MD5) — trocar em produção.');
    }
}
