<?php


namespace Database\Seeders\tgs;


class TgTipoContaBancaria {
    public static function tabela() {
        return [
            ["TABELA_ID" => 19, "COLUNA_ID" => 0, "DESCRICAO" => "TIPO_CONTA_BANCARIA", "ATIVO" => 1],
            ["TABELA_ID" => 19, "COLUNA_ID" => 1, "DESCRICAO" => "Pessoa Física", "ATIVO" => 1],
            ["TABELA_ID" => 19, "COLUNA_ID" => 2, "DESCRICAO" => "Pessoa Jurídica", "ATIVO" => 1],
        ];
    }
}
