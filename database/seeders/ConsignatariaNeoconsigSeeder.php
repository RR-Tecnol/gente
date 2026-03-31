<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConsignatariaNeoconsigSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotente — não duplica se já existir
        if (DB::table('CONSIGNATARIA')->where('CONSIGNATARIA_NOME', 'Neoconsig')->exists()) {
            $this->command->info('Neoconsig já cadastrada — seed ignorado.');
            return;
        }

        $id = DB::table('CONSIGNATARIA')->insertGetId([
            'CONSIGNATARIA_NOME'         => 'Neoconsig',
            'CONSIGNATARIA_CNPJ'         => null,
            'CONSIGNATARIA_CODIGO'       => 'NEO',
            'CONSIGNATARIA_TIPO'         => 'banco',
            'CONSIGNATARIA_ATIVA'        => true,
            'CONSIGNATARIA_MARGEM_MAX_PCT' => 30.00,
            'CONSIGNATARIA_CONTATO'      => null,
            'created_at'                 => now(),
            'updated_at'                 => now(),
        ]);

        $layouts = [
            // [NOME, DIRECAO, TAMANHO_LINHA, TIPO, FORMATO, MAPEAMENTO_JSON]
            [
                'NEOCONSIG_DEBITOS',
                'ENTRADA', 115,
                'remessa', 'txt',
                '{"matricula":[1,8],"nome":[9,38],"rubrica":[39,42],"competencia":[43,48],"valor_parcela":[49,60],"saldo_devedor":[61,72],"prazo_total":[73,75],"prazo_restante":[76,78],"id_operacao":[101,115]}'
            ],
            [
                'NEOCONSIG_RETFINANCEIRO',
                'ENTRADA', 66,
                'retorno', 'txt',
                '{"tipo_registro":[1,1],"header_trailer":[1,10],"competencia":[11,16],"matricula":[17,30],"rubrica":[31,34],"valor":[35,49],"id_operacao":[52,66]}'
            ],
            [
                'NEOCONSIG_RETQUITADAS',
                'ENTRADA', 66,
                'retorno', 'txt',
                '{"tipo_registro":[1,1],"competencia":[11,16],"matricula":[17,30],"rubrica":[31,34],"valor":[35,49],"id_operacao":[52,66]}'
            ],
            [
                'NEOCONSIG_CADASTRO',
                'SAIDA', 523,
                'remessa', 'txt',
                null  // mapeamento completo de 523 posições — a definir em B-IMP
            ],
            [
                'NEOCONSIG_FINANCEIRO',
                'SAIDA', 66,
                'remessa', 'txt',
                '{"matricula":[17,30],"rubrica":[31,34],"valor":[35,49],"id_operacao":[52,66]}'
            ],
        ];

        foreach ($layouts as [$nome, $direcao, $tamanho, $tipo, $formato, $mapa]) {
            DB::table('LAYOUT_CONSIGNATARIA')->insert([
                'CONSIGNATARIA_ID'      => $id,
                'LAYOUT_NOME'           => $nome,
                'LAYOUT_DIRECAO'        => $direcao,
                'LAYOUT_TAMANHO_LINHA'  => $tamanho,
                'LAYOUT_ENCODING'       => 'UTF-8',
                'LAYOUT_TIPO'           => $tipo,
                'LAYOUT_FORMATO'        => $formato,
                'LAYOUT_VERSAO'         => '1.0',
                'LAYOUT_MAPEAMENTO'     => $mapa,
                'LAYOUT_ATIVO'          => true,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);
        }

        $this->command->info("Neoconsig seedada com ID {$id} e 5 layouts.");
    }
}
