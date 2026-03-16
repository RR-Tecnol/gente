<?php


namespace Database\Seeders\tgs;


class TgSexo {
    public static function tabela() {
        return [
            ["TABELA_ID" => 2, "COLUNA_ID" => 0, "DESCRICAO" => "SEXO", "ATIVO" => 1],
            ["TABELA_ID" => 2, "COLUNA_ID" => 1, "DESCRICAO" => "Masculino", "ATIVO" => 1],
            ["TABELA_ID" => 2, "COLUNA_ID" => 2, "DESCRICAO" => "Feminino", "ATIVO" => 1],
            ["TABELA_ID" => 2, "COLUNA_ID" => 3, "DESCRICAO" => "Outros", "ATIVO" => 1],
        ];
    }
}
