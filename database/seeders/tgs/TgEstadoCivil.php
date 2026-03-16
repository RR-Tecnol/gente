<?php


namespace Database\Seeders\tgs;


class TgEstadoCivil {
    public static function tabela() {
        return [
            ["TABELA_ID" => 13, "COLUNA_ID" => 0, "DESCRICAO" => "ESTADO_CIVIL", "ATIVO" => 1],
            ["TABELA_ID" => 13, "COLUNA_ID" => 1, "DESCRICAO" => "Casado", "ATIVO" => 1],
            ["TABELA_ID" => 13, "COLUNA_ID" => 2, "DESCRICAO" => "Solteiro", "ATIVO" => 1],
        ];
    }
}
