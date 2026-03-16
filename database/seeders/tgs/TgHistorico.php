<?php


namespace Database\Seeders\tgs;


class TgHistorico {
    public static function tabela() {
        return [
            ["TABELA_ID" => 6, "COLUNA_ID" => 0, "DESCRICAO" => "HISTORICO", "ATIVO" => 1],
            ["TABELA_ID" => 6, "COLUNA_ID" => 1, "DESCRICAO" => "Criação", "ATIVO" => 1],
            ["TABELA_ID" => 6, "COLUNA_ID" => 2, "DESCRICAO" => "Edição", "ATIVO" => 1],
            ["TABELA_ID" => 6, "COLUNA_ID" => 3, "DESCRICAO" => "Exclusão", "ATIVO" => 1],
        ];
    }
}
