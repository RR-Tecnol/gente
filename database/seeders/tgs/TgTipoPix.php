<?php


namespace Database\Seeders\tgs;


class TgTipoPix {
    public static function tabela() {
        return [
            ["TABELA_ID" => 20, "COLUNA_ID" => 0, "DESCRICAO" => "TIPO_PIX", "ATIVO" => 1],
            ["TABELA_ID" => 20, "COLUNA_ID" => 1, "DESCRICAO" => "CPF / CNPJ", "ATIVO" => 1],
            ["TABELA_ID" => 20, "COLUNA_ID" => 2, "DESCRICAO" => "Email", "ATIVO" => 1],
            ["TABELA_ID" => 20, "COLUNA_ID" => 3, "DESCRICAO" => "Celular", "ATIVO" => 1],
        ];
    }
}
