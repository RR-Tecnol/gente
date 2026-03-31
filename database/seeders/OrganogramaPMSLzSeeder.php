<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrganogramaPMSLzSeeder extends Seeder
{
    public function run(): void
    {
        // Detectar colunas que podem ou não existir
        $temSigla = Schema::hasColumn('UNIDADE', 'UNIDADE_SIGLA');
        $temUniAtivo = Schema::hasColumn('UNIDADE', 'UNIDADE_ATIVO');
        $temSetorAtivo = Schema::hasColumn('SETOR', 'SETOR_ATIVO');
        $temUniTS = Schema::hasColumn('UNIDADE', 'updated_at');
        $temSetorTS = Schema::hasColumn('SETOR', 'updated_at');

        // ── SECRETARIAS (UNIDADE) ─────────────────────────────────────────────
        $secretarias = [
            ['GABPREF', 'Gabinete do Prefeito'],
            ['SEMAD', 'Secretaria Municipal de Administração'],
            ['SEMFAZ', 'Secretaria Municipal de Fazenda'],
            ['SEMED', 'Secretaria Municipal de Educação'],
            ['SEMUS', 'Secretaria Municipal de Saúde'],
            ['SEMCAS', 'Secretaria Municipal da Criança e Assistência Social'],
            ['SEMUSC', 'Secretaria Municipal de Segurança com Cidadania'],
            ['SEMOSP', 'Secretaria Municipal de Obras e Serviços Públicos'],
            ['SEMIT', 'Secretaria Municipal de Informação e Tecnologia'],
            ['SEPLAN', 'Secretaria Municipal de Planejamento e Desenvolvimento'],
            ['SMTT', 'Secretaria Municipal de Trânsito e Transporte'],
            ['SEMURH', 'Secretaria Municipal de Urbanismo e Habitação'],
            ['SEMAPA', 'Secretaria Municipal de Agricultura, Pesca e Abastecimento'],
            ['SECULT', 'Secretaria Municipal de Cultura'],
            ['SEMDEL', 'Secretaria Municipal de Desportos e Lazer'],
            ['SEMMAM', 'Secretaria Municipal de Meio Ambiente'],
            ['SEMGOV', 'Secretaria Municipal de Governo'],
            ['SEMISPE', 'Secretaria Municipal de Inovação, Sustentabilidade e Projetos Especiais'],
            ['SECOM', 'Secretaria Municipal de Comunicação'],
            ['SETUR', 'Secretaria Municipal de Turismo'],
            ['SEMSA', 'Secretaria Municipal de Segurança Alimentar'],
            ['SEMGOP', 'Secretaria Municipal de Governança Solidária e Orçamento Participativo'],
            ['SADEM', 'Secretaria Municipal de Articulação e Desenvolvimento Metropolitano'],
            ['SEMAI', 'Secretaria Municipal de Articulação Institucional'],
            ['SEMAP', 'Secretaria Municipal de Assuntos Políticos'],
            ['SEMEPED', 'Secretaria Municipal Extraordinária da Pessoa com Deficiência'],
        ];

        $unidadeIds = [];
        foreach ($secretarias as [$sigla, $nome]) {
            $data = ['UNIDADE_NOME' => $nome];
            if ($temSigla)
                $data['UNIDADE_SIGLA'] = $sigla;
            if ($temUniAtivo)
                $data['UNIDADE_ATIVO'] = 1;
            if ($temUniTS) {
                $data['updated_at'] = now();
                $data['created_at'] = now();
            }

            // Tentar encontrar pelo sigla ou pelo nome
            $existing = $temSigla
                ? DB::table('UNIDADE')->where('UNIDADE_SIGLA', $sigla)->first()
                : DB::table('UNIDADE')->where('UNIDADE_NOME', $nome)->first();

            if ($existing) {
                $uid = $existing->UNIDADE_ID;
                DB::table('UNIDADE')->where('UNIDADE_ID', $uid)->update($data);
            } else {
                if ($temSigla)
                    $data['UNIDADE_SIGLA'] = $sigla;
                $uid = DB::table('UNIDADE')->insertGetId($data);
            }
            $unidadeIds[$sigla] = $uid;
        }

        // ── SETORES por secretaria ────────────────────────────────────────────
        $setores = [
            'GABPREF' => ['Chefia de Gabinete', 'Assessoria Especial do Prefeito'],
            'SEMAD' => [
                'Gabinete do Secretário',
                'Superintendência de Recursos Humanos',
                'Coordenação de Folha de Pagamento',
                'Coordenação de Cadastro Funcional'
            ],
            'SEMFAZ' => [
                'Gabinete do Secretário',
                'Superintendência de Lançamentos e Arrecadação',
                'Contadoria Geral do Município',
                'Coordenação de Orçamento e Finanças'
            ],
            'SEMED' => [
                'Gabinete do Secretário',
                'Superintendência de Recursos Humanos',
                'Superintendência de Ensino Fundamental',
                'Superintendência de Educação Infantil'
            ],
            'SEMUS' => [
                'Gabinete do Secretário',
                'Superintendência de Assistência à Rede',
                'Hospital Municipal Djalma Marques',
                'Hospital Municipal Socorrão II',
                'Hospital Municipal Socorrão III'
            ],
            'SEMIT' => [
                'Gabinete do Secretário',
                'Superintendência de Recursos Tecnológicos',
                'Superintendência de Sistemas',
                'Coordenação de Banco de Dados'
            ],
            'SEMCAS' => ['Gabinete do Secretário', 'Coordenação de Assistência Social'],
            'SEMUSC' => ['Gabinete do Secretário', 'Superintendência da Guarda Municipal'],
            'SEMOSP' => ['Gabinete do Secretário', 'Superintendência de Obras'],
            'SEPLAN' => ['Gabinete do Secretário', 'Coordenação de Planejamento Estratégico'],
            'SMTT' => ['Gabinete do Secretário', 'Superintendência de Trânsito'],
            'SEMURH' => ['Gabinete do Secretário', 'Coordenação de Habitação'],
            'SEMAPA' => ['Gabinete do Secretário', 'Coordenação de Agricultura'],
            'SECULT' => ['Gabinete do Secretário', 'Coordenação de Eventos Culturais'],
            'SEMDEL' => ['Gabinete do Secretário', 'Coordenação de Esportes'],
            'SEMMAM' => ['Gabinete do Secretário', 'Coordenação de Meio Ambiente'],
            'SEMGOV' => ['Gabinete do Secretário', 'Coordenação de Articulação Política'],
            'SEMISPE' => ['Gabinete do Secretário', 'Coordenação de Inovação'],
            'SECOM' => ['Gabinete do Secretário', 'Coordenação de Comunicação Social'],
            'SETUR' => ['Gabinete do Secretário', 'Coordenação de Turismo'],
            'SEMSA' => ['Gabinete do Secretário', 'Coordenação de Segurança Alimentar'],
            'SEMGOP' => ['Gabinete do Secretário', 'Coordenação de Orçamento Participativo'],
            'SADEM' => ['Gabinete do Secretário', 'Coordenação Metropolitana'],
            'SEMAI' => ['Gabinete do Secretário', 'Coordenação Institucional'],
            'SEMAP' => ['Gabinete do Secretário', 'Assessoria Política'],
            'SEMEPED' => ['Gabinete do Secretário', 'Coordenação de Inclusão'],
        ];

        $totalSetores = 0;
        foreach ($setores as $sigla => $nomes) {
            $uid = $unidadeIds[$sigla] ?? null;
            if (!$uid)
                continue;
            foreach ($nomes as $nome) {
                if (!DB::table('SETOR')->where('SETOR_NOME', $nome)->where('UNIDADE_ID', $uid)->exists()) {
                    $data = ['SETOR_NOME' => $nome, 'UNIDADE_ID' => $uid];
                    if ($temSetorAtivo)
                        $data['SETOR_ATIVO'] = 1;
                    if ($temSetorTS) {
                        $data['updated_at'] = now();
                        $data['created_at'] = now();
                    }
                    DB::table('SETOR')->insert($data);
                }
                $totalSetores++;
            }
        }

        $this->command->info("✅ OrganogramaPMSLzSeeder: " . count($secretarias) . " secretarias e {$totalSetores} setores inseridos.");
    }
}
