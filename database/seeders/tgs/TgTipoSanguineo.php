<?php


namespace Database\Seeders\tgs;


class TgTipoSanguineo {
    public static function tabela() {
        return [
            ["TABELA_ID" => 14, "COLUNA_ID" => 0, "DESCRICAO" => "TIPO_SANGUÍNEO", "ATIVO" => 1],
            ["TABELA_ID" => 14, "COLUNA_ID" => 1, "DESCRICAO" => "O", "ATIVO" => 1],
            ["TABELA_ID" => 14, "COLUNA_ID" => 2, "DESCRICAO" => "A", "ATIVO" => 1],
            ["TABELA_ID" => 14, "COLUNA_ID" => 3, "DESCRICAO" => "B", "ATIVO" => 1],
            ["TABELA_ID" => 14, "COLUNA_ID" => 4, "DESCRICAO" => "AB", "ATIVO" => 1],
        ];
    }
}
