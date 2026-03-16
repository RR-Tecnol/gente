<?php


namespace Database\Seeders\tgs;


class TgDocumentosPessoais {
    public static function tabela() {
        return [
            ["TABELA_ID" => 4, "COLUNA_ID" => 0, "DESCRICAO" => "DOCUMENTOS_PESSOAIS", "ATIVO" => 0],
            ["TABELA_ID" => 4, "COLUNA_ID" => 1, "DESCRICAO" => "RG", "ATIVO" => 1],
            ["TABELA_ID" => 4, "COLUNA_ID" => 2, "DESCRICAO" => "CPF", "ATIVO" => 1],
        ];
    }
}
