<?php


namespace Database\Seeders\tgs;


class TgStatus {
    public static function tabela() {
        return [
            ["TABELA_ID" => 8, "COLUNA_ID" => 0, "DESCRICAO" => "STATUS", "ATIVO" => 1],
            ["TABELA_ID" => 8, "COLUNA_ID" => 1, "DESCRICAO" => "Pendente", "ATIVO" => 1],
            ["TABELA_ID" => 8, "COLUNA_ID" => 2, "DESCRICAO" => "Cancelado", "ATIVO" => 1],
            ["TABELA_ID" => 8, "COLUNA_ID" => 3, "DESCRICAO" => "Concluído", "ATIVO" => 1],
        ];
    }
}
