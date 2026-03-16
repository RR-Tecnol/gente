<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoDocumentoSeeder extends Seeder {
    public function run() {
        $rows = [
            ["TIPO_DOCUMENTO_DESCRICAO" => "RG",                                                    "TIPO_DOCUMENTO_ATIVO" => 1, "TIPO_DOCUMENTO_OBRIGATORIO" => 1],
            ["TIPO_DOCUMENTO_DESCRICAO" => "CPF",                                                   "TIPO_DOCUMENTO_ATIVO" => 1, "TIPO_DOCUMENTO_OBRIGATORIO" => 1],
            ["TIPO_DOCUMENTO_DESCRICAO" => "CNH",                                                   "TIPO_DOCUMENTO_ATIVO" => 0, "TIPO_DOCUMENTO_OBRIGATORIO" => 0],
            ["TIPO_DOCUMENTO_DESCRICAO" => "CARTEIRA DE HABILITAÇÃO",                               "TIPO_DOCUMENTO_ATIVO" => 1, "TIPO_DOCUMENTO_OBRIGATORIO" => 0],
            ["TIPO_DOCUMENTO_DESCRICAO" => "CRA",                                                   "TIPO_DOCUMENTO_ATIVO" => 1, "TIPO_DOCUMENTO_OBRIGATORIO" => 0],
            ["TIPO_DOCUMENTO_DESCRICAO" => "CARTEIRA DE TRABALHO",                                  "TIPO_DOCUMENTO_ATIVO" => 1, "TIPO_DOCUMENTO_OBRIGATORIO" => 0],
            ["TIPO_DOCUMENTO_DESCRICAO" => "CERTIDÃO NEGATIVA DE ANTECEDENTES CRIMINAIS",           "TIPO_DOCUMENTO_ATIVO" => 1, "TIPO_DOCUMENTO_OBRIGATORIO" => 0],
            ["TIPO_DOCUMENTO_DESCRICAO" => "DIPLOMA",                                               "TIPO_DOCUMENTO_ATIVO" => 0, "TIPO_DOCUMENTO_OBRIGATORIO" => 0],
            ["TIPO_DOCUMENTO_DESCRICAO" => "RG2",                                                   "TIPO_DOCUMENTO_ATIVO" => 0, "TIPO_DOCUMENTO_OBRIGATORIO" => 0],
            ["TIPO_DOCUMENTO_DESCRICAO" => "IDENTIDADE",                                            "TIPO_DOCUMENTO_ATIVO" => 0, "TIPO_DOCUMENTO_OBRIGATORIO" => 0],
            ["TIPO_DOCUMENTO_DESCRICAO" => "COMPROVANTE DE RESIDÊNCIA COM NÚMERO DO CEP",           "TIPO_DOCUMENTO_ATIVO" => 1, "TIPO_DOCUMENTO_OBRIGATORIO" => 0],
            ["TIPO_DOCUMENTO_DESCRICAO" => "DIPLOMA DE ENSINO MÉDIO",                               "TIPO_DOCUMENTO_ATIVO" => 1, "TIPO_DOCUMENTO_OBRIGATORIO" => 0],
            ["TIPO_DOCUMENTO_DESCRICAO" => "DIPLOMA DE ENSINO SUPERIOR",                            "TIPO_DOCUMENTO_ATIVO" => 1, "TIPO_DOCUMENTO_OBRIGATORIO" => 0],
            ["TIPO_DOCUMENTO_DESCRICAO" => "CARTEIRA DE TRABALHO (1ª FOLHA FRENTE E VERSO)",        "TIPO_DOCUMENTO_ATIVO" => 1, "TIPO_DOCUMENTO_OBRIGATORIO" => 0],
            ["TIPO_DOCUMENTO_DESCRICAO" => "CARTEIRA DE CONSELHO DE CLASSE(REGISTRO PROFISSIONAL)", "TIPO_DOCUMENTO_ATIVO" => 1, "TIPO_DOCUMENTO_OBRIGATORIO" => 0],
            ["TIPO_DOCUMENTO_DESCRICAO" => "TÍTULO DE ELEITOR",                                     "TIPO_DOCUMENTO_ATIVO" => 1, "TIPO_DOCUMENTO_OBRIGATORIO" => 0],
            ["TIPO_DOCUMENTO_DESCRICAO" => "PIS/PASEP/NIT",                                         "TIPO_DOCUMENTO_ATIVO" => 1, "TIPO_DOCUMENTO_OBRIGATORIO" => 0],
            ["TIPO_DOCUMENTO_DESCRICAO" => "CERTIDÃO NASCIMENTO OU CASAMENTO",                      "TIPO_DOCUMENTO_ATIVO" => 1, "TIPO_DOCUMENTO_OBRIGATORIO" => 0],
            ["TIPO_DOCUMENTO_DESCRICAO" => "CONTA BANCÁRIA (CARTÃO)",                               "TIPO_DOCUMENTO_ATIVO" => 1, "TIPO_DOCUMENTO_OBRIGATORIO" => 0],
            ["TIPO_DOCUMENTO_DESCRICAO" => "ANTECEDENTE CRIMINAL",                                  "TIPO_DOCUMENTO_ATIVO" => 1, "TIPO_DOCUMENTO_OBRIGATORIO" => 0],
            ["TIPO_DOCUMENTO_DESCRICAO" => "REGISTRO DO CONSELHO",                                  "TIPO_DOCUMENTO_ATIVO" => 1, "TIPO_DOCUMENTO_OBRIGATORIO" => 0],
        ];
//        DB::statement("SET IDENTITY_INSERT TIPO_DOCUMENTO ON");
        DB::table("TIPO_DOCUMENTO")->insert($rows);
//        DB::statement("SET IDENTITY_INSERT TIPO_DOCUMENTO OFF");
    }
}
