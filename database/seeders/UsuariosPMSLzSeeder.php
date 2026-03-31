<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Cria usuários de teste para todos os 15 perfis do sistema.
 * Vincula cada usuário ao funcionário correspondente do FuncionariosPMSLzSeeder.
 * Senhas: md5('gente@2026') para todos exceto sisgep (md5('sisgep@2026')).
 *
 * php artisan db:seed --class=UsuariosPMSLzSeeder
 */
class UsuariosPMSLzSeeder extends Seeder
{
    public function run(): void
    {
        $senhaPadrao = md5('gente@2026');
        $senhaSisgep = md5('sisgep@2026');

        // [login, nome, perfil_id, matricula_funcionario]
        $usuarios = [
            ['2026-0001', 'Ana Cristina Barros', 5, '2026-0001'],  // Externo
            ['2026-0002', 'José Carlos Lima', 6, '2026-0002'],  // RH Folha
            ['2026-0003', 'Maria das Dores Silva', 3, '2026-0003'],  // Operacional
            ['2026-0004', 'Francisco Ramos Costa', 15, '2026-0004'],  // RH Rede
            ['2026-0005', 'Antônia Pereira Nunes', 14, '2026-0005'],  // RH APS
            ['2026-0006', 'Raimundo Sousa Farias', 7, '2026-0006'],  // Gestão
            ['2026-0007', 'Luciana Moura Castro', 11, '2026-0007'],  // Coordenador de Setor
            ['2026-0008', 'Pedro Henrique Alves', 12, '2026-0008'],  // Diretor/Gestor de Unidade
            ['2026-0009', 'Cláudia Regina Santos', 8, '2026-0009'],  // RH Unidade
            ['2026-0010', 'Roberto Fonseca Melo', 5, '2026-0010'],  // Externo (SP)
            ['2026-0012', 'Geraldo Augusto Reis', 5, '2026-0012'],  // Externo (CC)
            ['2026-0013', 'Silvana Monteiro Cruz', 9, '2026-0013'],  // Direitos e Deveres
            ['2026-0014', 'Marcos Vinícius Neto', 7, '2026-0014'],  // Gestão (FC)
            ['2026-0016', 'Carlos Eduardo Brito', 10, '2026-0016'],  // Recrutador
            ['2026-0018', 'Danielle Souza Cunha', 4, '2026-0018'],  // Manutenção
        ];

        foreach ($usuarios as [$login, $nome, $perfilId, $matricula]) {
            // Busca o FUNCIONARIO_ID pela matrícula
            $funcId = DB::table('FUNCIONARIO')
                ->where('FUNCIONARIO_MATRICULA', $matricula)
                ->value('FUNCIONARIO_ID');

            // Cria ou reutiliza o usuário
            $existente = DB::table('USUARIO')
                ->where('USUARIO_LOGIN', $login)->first();

            if (!$existente) {
                $usuarioId = DB::table('USUARIO')->insertGetId([
                    'USUARIO_LOGIN' => $login,
                    'USUARIO_SENHA' => $senhaPadrao,
                    'USUARIO_NOME' => $nome,
                    'USUARIO_ATIVO' => 1,
                ]);
            } else {
                $usuarioId = $existente->USUARIO_ID;
            }

            // Vincula ao funcionário
            if ($funcId) {
                DB::table('FUNCIONARIO')
                    ->where('FUNCIONARIO_ID', $funcId)
                    ->update(['USUARIO_ID' => $usuarioId]);
            }

            // Vincula o perfil (cria ou garante que existe)
            DB::table('USUARIO_PERFIL')->updateOrInsert(
                ['USUARIO_ID' => $usuarioId, 'PERFIL_ID' => $perfilId],
                ['USUARIO_PERFIL_ATIVO' => 1]
            );

            $this->command->line("  ✓ [{$login}] {$nome} → Perfil {$perfilId}");
        }

        // Usuário técnico — Equipe SISGEP (sem vínculo com funcionário)
        $sisgepExiste = DB::table('USUARIO')
            ->where('USUARIO_LOGIN', 'sisgep')->first();

        if (!$sisgepExiste) {
            $sisgepId = DB::table('USUARIO')->insertGetId([
                'USUARIO_LOGIN' => 'sisgep',
                'USUARIO_SENHA' => $senhaSisgep,
                'USUARIO_NOME' => 'Equipe SISGEP',
                'USUARIO_ATIVO' => 1,
            ]);
            DB::table('USUARIO_PERFIL')->insert([
                'USUARIO_ID' => $sisgepId,
                'PERFIL_ID' => 13,
                'USUARIO_PERFIL_ATIVO' => 1,
            ]);
            $this->command->line('  ✓ [sisgep] Equipe SISGEP → Perfil 13');
        }

        $this->command->info('✅ ' . (count($usuarios) + 1) . ' usuários criados/verificados.');
        $this->command->warn('   Senhas padrão: gente@2026 | sisgep@2026');
        $this->command->warn('   Trocar em produção!');
    }
}
