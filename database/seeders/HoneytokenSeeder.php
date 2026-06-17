<?php

namespace Database\Seeders;

use App\Security\HoneytokenRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 5 servidores isca (Frente 3). Não ligado no DatabaseSeeder — executar em homolog:
 * php artisan db:seed --class=HoneytokenSeeder
 */
class HoneytokenSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('PESSOA') || ! Schema::hasTable('FUNCIONARIO')) {
            $this->command?->warn('HoneytokenSeeder: tabelas PESSOA/FUNCIONARIO inexistentes.');

            return;
        }
        if (! Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_HONEYTOKEN')) {
            $this->command?->warn('HoneytokenSeeder: rode a migration honeytokens (FUNCIONARIO_HONEYTOKEN).');

            return;
        }

        $setorId = (int) (DB::table('SETOR')->orderBy('SETOR_ID')->value('SETOR_ID') ?? 0);
        if (! $setorId) {
            $this->command?->error('HoneytokenSeeder: nenhum SETOR na base.');

            return;
        }

        $vinculoId = Schema::hasTable('VINCULO')
            ? (int) (DB::table('VINCULO')->orderBy('VINCULO_ID')->value('VINCULO_ID') ?? 0)
            : 0;

        $honey = [
            ['nome' => 'Administrador de Sistema Reserva (HONEY)', 'mat' => 'HNY-900001'],
            ['nome' => 'Auditor Fiscal de Teste — Não tocar (HONEY)', 'mat' => 'HNY-900002'],
            ['nome' => 'Coordenador de Auditoria Especial — Isca GENTE', 'mat' => 'HNY-900003'],
            ['nome' => 'Secretário-Executivo Provisório (Monitoramento)', 'mat' => 'HNY-900004'],
            ['nome' => 'Analista de Dados Estratégicos — Acesso Contingente', 'mat' => 'HNY-900005'],
        ];

        $cpfCol = Schema::hasColumn('PESSOA', 'PESSOA_CPF_NUMERO') ? 'PESSOA_CPF_NUMERO' : (Schema::hasColumn('PESSOA', 'PESSOA_CPF') ? 'PESSOA_CPF' : null);
        if (! $cpfCol) {
            $this->command?->error('HoneytokenSeeder: coluna de CPF em PESSOA não encontrada.');

            return;
        }

        foreach ($honey as $i => $row) {
            $cpf = str_pad((string) (990000000 + $i), 11, '0', STR_PAD_LEFT);
            $pessoa = [
                'PESSOA_NOME' => $row['nome'],
                $cpfCol => $cpf,
            ];
            if (Schema::hasColumn('PESSOA', 'PESSOA_CPF') && $cpfCol === 'PESSOA_CPF_NUMERO') {
                $pessoa['PESSOA_CPF'] = $cpf;
            }
            if (Schema::hasColumn('PESSOA', 'PESSOA_ATIVO')) {
                $pessoa['PESSOA_ATIVO'] = 1;
            }
            $pid = (int) DB::table('PESSOA')->insertGetId($pessoa);

            $f = [
                'PESSOA_ID' => $pid,
                'FUNCIONARIO_MATRICULA' => $row['mat'],
                'FUNCIONARIO_HONEYTOKEN' => 1,
            ];
            if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_ATIVO')) {
                $f['FUNCIONARIO_ATIVO'] = 1;
            }
            if (Schema::hasColumn('FUNCIONARIO', 'VINCULO_ID') && $vinculoId) {
                $f['VINCULO_ID'] = $vinculoId;
            }
            if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_INICIO')) {
                $f['FUNCIONARIO_DATA_INICIO'] = '2010-01-01';
            }
            $fid = (int) DB::table('FUNCIONARIO')->insertGetId($f);

            if (Schema::hasTable('LOTACAO')) {
                $l = [
                    'FUNCIONARIO_ID' => $fid,
                    'SETOR_ID' => $setorId,
                ];
                if (Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_INICIO')) {
                    $l['LOTACAO_DATA_INICIO'] = '2010-01-01';
                }
                if (Schema::hasColumn('LOTACAO', 'VINCULO_ID') && $vinculoId) {
                    $l['VINCULO_ID'] = $vinculoId;
                }
                DB::table('LOTACAO')->insert($l);
            }

            $this->command?->line("Honeytoken: FUNCIONARIO_ID={$fid} — {$row['nome']}");
        }

        HoneytokenRegistry::forgetCache();
        $this->command?->info('HoneytokenSeeder concluído. Cache de IDs de isca limpo.');
    }
}
