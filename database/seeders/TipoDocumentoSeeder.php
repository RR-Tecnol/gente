<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TipoDocumentoSeeder extends Seeder {
    public function run() {
        if (!Schema::hasTable('TIPO_DOCUMENTO')) {
            $this->command->warn('Tabela TIPO_DOCUMENTO não existe — seeder ignorado.');
            return;
        }

        // Detecta nome da coluna principal: PMSL usa TIPO_DOCUMENTO_NOME,
        // schemas legados podem ter TIPO_DOCUMENTO_DESCRICAO.
        $colNome = Schema::hasColumn('TIPO_DOCUMENTO', 'TIPO_DOCUMENTO_NOME')
            ? 'TIPO_DOCUMENTO_NOME'
            : (Schema::hasColumn('TIPO_DOCUMENTO', 'TIPO_DOCUMENTO_DESCRICAO') ? 'TIPO_DOCUMENTO_DESCRICAO' : null);

        if (!$colNome) {
            $this->command->error('TIPO_DOCUMENTO sem coluna de nome (NOME ou DESCRICAO) — seeder abortado.');
            return;
        }

        // TIPO_DOCUMENTO_OBRIGATORIO não existe em PMSL — só preenche se a coluna existir
        $temObrigatorio = Schema::hasColumn('TIPO_DOCUMENTO', 'TIPO_DOCUMENTO_OBRIGATORIO');

        $tipos = [
            ['nome' => 'RG',                                                    'ativo' => 1, 'obrigatorio' => 1],
            ['nome' => 'CPF',                                                   'ativo' => 1, 'obrigatorio' => 1],
            ['nome' => 'CNH',                                                   'ativo' => 0, 'obrigatorio' => 0],
            ['nome' => 'CARTEIRA DE HABILITAÇÃO',                               'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'CRA',                                                   'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'CARTEIRA DE TRABALHO',                                  'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'CERTIDÃO NEGATIVA DE ANTECEDENTES CRIMINAIS',           'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'DIPLOMA',                                               'ativo' => 0, 'obrigatorio' => 0],
            ['nome' => 'RG2',                                                   'ativo' => 0, 'obrigatorio' => 0],
            ['nome' => 'IDENTIDADE',                                            'ativo' => 0, 'obrigatorio' => 0],
            ['nome' => 'COMPROVANTE DE RESIDÊNCIA COM NÚMERO DO CEP',           'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'DIPLOMA DE ENSINO MÉDIO',                               'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'DIPLOMA DE ENSINO SUPERIOR',                            'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'CARTEIRA DE TRABALHO (1ª FOLHA FRENTE E VERSO)',        'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'CARTEIRA DE CONSELHO DE CLASSE(REGISTRO PROFISSIONAL)', 'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'TÍTULO DE ELEITOR',                                     'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'PIS/PASEP/NIT',                                         'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'CERTIDÃO NASCIMENTO OU CASAMENTO',                      'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'CONTA BANCÁRIA (CARTÃO)',                               'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'ANTECEDENTE CRIMINAL',                                  'ativo' => 1, 'obrigatorio' => 0],
            ['nome' => 'REGISTRO DO CONSELHO',                                  'ativo' => 1, 'obrigatorio' => 0],
        ];

        foreach ($tipos as $t) {
            $payload = [
                'TIPO_DOCUMENTO_ATIVO' => $t['ativo'],
            ];
            if ($temObrigatorio) {
                $payload['TIPO_DOCUMENTO_OBRIGATORIO'] = $t['obrigatorio'];
            }
            DB::table('TIPO_DOCUMENTO')->updateOrInsert(
                [$colNome => $t['nome']],
                $payload
            );
        }

        $this->command->info('TipoDocumentoSeeder: ' . count($tipos) . ' tipos garantidos.');
    }
}
