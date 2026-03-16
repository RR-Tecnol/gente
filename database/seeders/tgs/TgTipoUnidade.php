<?php


namespace Database\Seeders\tgs;


class TgTipoUnidade {
    public static function tabela() {
        return [
            ["TABELA_ID" => 10, "COLUNA_ID" => 0, "DESCRICAO" => "TIPO_UNIDADE", "ATIVO" => 1],
            ["TABELA_ID" => 10, "COLUNA_ID" => 1, "DESCRICAO" => "Administrativa", "ATIVO" => 1],
            ["TABELA_ID" => 10, "COLUNA_ID" => 2, "DESCRICAO" => "Hospitalar", "ATIVO" => 1],
            ["TABELA_ID" => 10, "COLUNA_ID" => 3, "DESCRICAO" => "Ambulatorial", "ATIVO" => 1],
        ];
    }
}
