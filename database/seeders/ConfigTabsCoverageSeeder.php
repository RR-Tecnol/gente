<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConfigTabsCoverageSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBancos();
        $this->seedUfsCidadesBairros();
        $this->seedConselhos();
        $this->seedTiposDocumento();
        $this->seedTurnos();
        $this->seedEventosFolha();
        $this->fixVinculos();
    }

    private function seedBancos(): void
    {
        if (!Schema::hasTable('BANCO')) return;
        foreach ([['001', 'Banco do Brasil'], ['104', 'Caixa Econômica Federal'], ['237', 'Bradesco'], ['341', 'Itaú Unibanco']] as [$codigo, $nome]) {
            DB::table('BANCO')->updateOrInsert(
                ['BANCO_CODIGO' => $codigo],
                ['BANCO_NOME' => $nome]
            );
        }
    }

    private function seedUfsCidadesBairros(): void
    {
        if (!Schema::hasTable('UF') || !Schema::hasTable('CIDADE')) return;
        $colsUf = Schema::getColumnListing('UF');
        $colsCidade = Schema::getColumnListing('CIDADE');

        $ufs = [
            ['MA', 'Maranhão', 'Nordeste'],
            ['PA', 'Pará', 'Norte'],
            ['PI', 'Piauí', 'Nordeste'],
        ];
        foreach ($ufs as [$sigla, $nome, $regiao]) {
            $payload = ['UF_NOME' => $nome];
            if (in_array('UF_REGIAO', $colsUf, true)) {
                $payload['UF_REGIAO'] = $regiao;
            }
            DB::table('UF')->updateOrInsert(['UF_SIGLA' => $sigla], $payload);
        }
        $ufMaId = DB::table('UF')->where('UF_SIGLA', 'MA')->value('UF_ID');
        if (!$ufMaId) return;

        $cidades = ['São Luís', 'São José de Ribamar', 'Paço do Lumiar', 'Raposa'];
        foreach ($cidades as $cidade) {
            $payload = [];
            if (in_array('CIDADE_IBGE', $colsCidade, true)) {
                $payload['CIDADE_IBGE'] = null;
            }
            DB::table('CIDADE')->updateOrInsert(['CIDADE_NOME' => $cidade, 'UF_ID' => $ufMaId], $payload);
        }
        if (!Schema::hasTable('BAIRRO')) return;
        $cidadeSlz = DB::table('CIDADE')->where('CIDADE_NOME', 'São Luís')->where('UF_ID', $ufMaId)->value('CIDADE_ID');
        if (!$cidadeSlz) return;
        foreach (['Cohama', 'Renascença', 'Turu', 'Cohab', 'Anjo da Guarda'] as $bairro) {
            DB::table('BAIRRO')->updateOrInsert(['BAIRRO_NOME' => $bairro, 'CIDADE_ID' => $cidadeSlz], []);
        }
    }

    private function seedConselhos(): void
    {
        if (!Schema::hasTable('CONSELHO')) return;
        foreach ([['CRM', 'Conselho Regional de Medicina'], ['COREN', 'Conselho Regional de Enfermagem'], ['CRF', 'Conselho Regional de Farmácia']] as [$sigla, $nome]) {
            DB::table('CONSELHO')->updateOrInsert(['CONSELHO_SIGLA' => $sigla], ['CONSELHO_NOME' => $nome]);
        }
    }

    private function seedTiposDocumento(): void
    {
        if (!Schema::hasTable('TIPO_DOCUMENTO')) return;
        $cols = Schema::getColumnListing('TIPO_DOCUMENTO');
        foreach ([['CPF', 'Cadastro de Pessoa Física'], ['RG', 'Registro Geral'], ['CNH', 'Carteira Nacional de Habilitação'], ['CTPS', 'Carteira de Trabalho']] as [$codigo, $nome]) {
            $payload = [];
            if (in_array('TIPO_DOCUMENTO_CODIGO', $cols, true)) {
                $payload['TIPO_DOCUMENTO_CODIGO'] = $codigo;
            }
            DB::table('TIPO_DOCUMENTO')->updateOrInsert(
                ['TIPO_DOCUMENTO_NOME' => $nome],
                $payload
            );
        }
    }

    private function seedTurnos(): void
    {
        if (!Schema::hasTable('TURNO')) return;
        $cols = Schema::getColumnListing('TURNO');
        $turnos = [
            ['M', 'Matutino', '08:00', '12:00', 4],
            ['V', 'Vespertino', '13:00', '17:00', 4],
            ['N', 'Noturno', '19:00', '07:00', 12],
            ['I', 'Integral', '08:00', '17:00', 8],
        ];
        foreach ($turnos as [$sigla, $nome, $inicio, $fim, $carga]) {
            $payload = ['TURNO_SIGLA' => $sigla];
            if (in_array('TURNO_NOME', $cols, true)) $payload['TURNO_NOME'] = $nome;
            if (in_array('TURNO_DESCRICAO', $cols, true)) $payload['TURNO_DESCRICAO'] = $nome;
            if (in_array('TURNO_HORA_ENTRADA', $cols, true)) $payload['TURNO_HORA_ENTRADA'] = $inicio;
            if (in_array('TURNO_HORA_SAIDA', $cols, true)) $payload['TURNO_HORA_SAIDA'] = $fim;
            if (in_array('TURNO_HORA_INICIO', $cols, true)) $payload['TURNO_HORA_INICIO'] = $inicio;
            if (in_array('TURNO_HORA_FIM', $cols, true)) $payload['TURNO_HORA_FIM'] = $fim;
            if (in_array('TURNO_CARGA_HORARIA', $cols, true)) $payload['TURNO_CARGA_HORARIA'] = $carga;
            if (in_array('TURNO_ATIVO', $cols, true)) $payload['TURNO_ATIVO'] = 1;
            DB::table('TURNO')->updateOrInsert(['TURNO_SIGLA' => $sigla], $payload);
        }
    }

    private function seedEventosFolha(): void
    {
        if (!Schema::hasTable('EVENTO')) return;
        $cols = Schema::getColumnListing('EVENTO');
        $eventos = [
            ['1001', 'Salário Base', 'P', 1, 1, 1],
            ['1010', 'Adicional Noturno', 'P', 1, 1, 1],
            ['2001', 'INSS', 'D', 0, 0, 0],
            ['2002', 'IRRF', 'D', 0, 0, 0],
        ];
        foreach ($eventos as [$codigo, $nome, $tipo, $inss, $irrf, $fgts]) {
            $payload = ['EVENTO_CODIGO' => $codigo];
            if (in_array('EVENTO_DESCRICAO', $cols, true)) $payload['EVENTO_DESCRICAO'] = $nome;
            if (in_array('EVENTO_NOME', $cols, true)) $payload['EVENTO_NOME'] = $nome;
            if (in_array('EVENTO_TIPO', $cols, true)) $payload['EVENTO_TIPO'] = $tipo;
            if (in_array('EVENTO_INCIDE_INSS', $cols, true)) $payload['EVENTO_INCIDE_INSS'] = $inss;
            if (in_array('EVENTO_INCIDE_IRRF', $cols, true)) $payload['EVENTO_INCIDE_IRRF'] = $irrf;
            if (in_array('EVENTO_INCIDE_FGTS', $cols, true)) $payload['EVENTO_INCIDE_FGTS'] = $fgts;
            if (in_array('EVENTO_ATIVO', $cols, true)) $payload['EVENTO_ATIVO'] = 1;
            if (in_array('created_at', $cols, true)) $payload['created_at'] = now();
            if (in_array('updated_at', $cols, true)) $payload['updated_at'] = now();
            DB::table('EVENTO')->updateOrInsert(['EVENTO_CODIGO' => $codigo], $payload);
        }
    }

    private function fixVinculos(): void
    {
        if (!Schema::hasTable('VINCULO')) return;
        $cols = Schema::getColumnListing('VINCULO');
        $linhas = DB::table('VINCULO')->get();
        foreach ($linhas as $v) {
            $nome = trim((string) ($v->VINCULO_NOME ?? ''));
            if ($nome === '') continue;
            $sigla = trim((string) ($v->VINCULO_SIGLA ?? ''));
            if ($sigla === '') {
                $sigla = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $nome), 0, 4));
            }
            $patch = ['VINCULO_SIGLA' => $sigla ?: 'VINC'];
            if (in_array('VINCULO_DESCRICAO', $cols, true) && empty($v->VINCULO_DESCRICAO)) {
                $patch['VINCULO_DESCRICAO'] = $nome;
            }
            if (in_array('VINCULO_ATIVO', $cols, true) && (int) ($v->VINCULO_ATIVO ?? 0) === 0) {
                $patch['VINCULO_ATIVO'] = 1;
            }
            DB::table('VINCULO')->where('VINCULO_ID', $v->VINCULO_ID)->update($patch);
        }
    }
}
