<?php


namespace Database\Seeders\tgs;


class TgContatoTipo {
    public static function tabela() {
        return [
            ["TABELA_ID" => 3, "COLUNA_ID" => 0, "DESCRICAO" => "CONTATO_TIPO", "ATIVO" => 1],
            ["TABELA_ID" => 3, "COLUNA_ID" => 1, "DESCRICAO" => "Telefone", "ATIVO" => 1],
            ["TABELA_ID" => 3, "COLUNA_ID" => 2, "DESCRICAO" => "Email", "ATIVO" => 1],
            ["TABELA_ID" => 3, "COLUNA_ID" => 3, "DESCRICAO" => "Celular", "ATIVO" => 1],
        ];
    }
}
