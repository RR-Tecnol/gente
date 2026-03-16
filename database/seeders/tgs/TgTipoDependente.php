<?php


namespace Database\Seeders\tgs;


class TgTipoDependente {
    public static function tabela() {
        return [
            ["TABELA_ID" => 11, "COLUNA_ID" => 0, "DESCRICAO" => "TIPO_DEPENDENTE", "ATIVO" => 1],
            ["TABELA_ID" => 11, "COLUNA_ID" => 1, "DESCRICAO" => "Filho(a)", "ATIVO" => 1],
            ["TABELA_ID" => 11, "COLUNA_ID" => 2, "DESCRICAO" => "Neto(a)", "ATIVO" => 1],
            ["TABELA_ID" => 11, "COLUNA_ID" => 3, "DESCRICAO" => "Cônjuge", "ATIVO" => 1],
        ];
    }
}
