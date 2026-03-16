<?php


namespace Database\Seeders\tgs;


class TgEscolaridade {
    public static function tabela() {
        return [
            ["TABELA_ID" => 1, "COLUNA_ID" => 0, "DESCRICAO" => "ESCOLARIDADE", "ATIVO" => 1],
            ["TABELA_ID" => 1, "COLUNA_ID" => 1, "DESCRICAO" => "Ensino Fundamental", "ATIVO" => 1],
            ["TABELA_ID" => 1, "COLUNA_ID" => 2, "DESCRICAO" => "Ensino Médio", "ATIVO" => 1],
            ["TABELA_ID" => 1, "COLUNA_ID" => 3, "DESCRICAO" => "Ensino Superior", "ATIVO" => 1],
            ["TABELA_ID" => 1, "COLUNA_ID" => 4, "DESCRICAO" => "Especialização", "ATIVO" => 1],
            ["TABELA_ID" => 1, "COLUNA_ID" => 5, "DESCRICAO" => "Mestrado", "ATIVO" => 1],
            ["TABELA_ID" => 1, "COLUNA_ID" => 6, "DESCRICAO" => "Doutorado", "ATIVO" => 1],
        ];
    }
}
