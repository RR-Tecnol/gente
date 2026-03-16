<?php


namespace Database\Seeders\tgs;


class TgTipoStatusScala {
    public static function tabela() {
        return [
            ["TABELA_ID" => 27, "COLUNA_ID" => 0, "DESCRICAO" => "TIPO_STATUS_ESCALA", "ATIVO" => 1],
            ["TABELA_ID" => 27, "COLUNA_ID" => 1, "DESCRICAO" => "Cadastrada", "ATIVO" => 1],
            ["TABELA_ID" => 27, "COLUNA_ID" => 2, "DESCRICAO" => "Atualizada", "ATIVO" => 1],
            ["TABELA_ID" => 27, "COLUNA_ID" => 3, "DESCRICAO" => "Avaliada", "ATIVO" => 1],
            ["TABELA_ID" => 27, "COLUNA_ID" => 4, "DESCRICAO" => "Deferida", "ATIVO" => 1],
            ["TABELA_ID" => 27, "COLUNA_ID" => 5, "DESCRICAO" => "Indeferida", "ATIVO" => 1],
        ];
    }
}
