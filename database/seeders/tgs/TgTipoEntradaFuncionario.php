<?php


namespace Database\Seeders\tgs;


class TgTipoEntradaFuncionario {
    public static function tabela() {
        return [
            ["TABELA_ID" => 22, "COLUNA_ID" => 0, "DESCRICAO" => "TIPO_ENTRADA_FUNCIONARIO", "ATIVO" => 1],
            ["TABELA_ID" => 22, "COLUNA_ID" => 1, "DESCRICAO" => "Concurso", "ATIVO" => 1],
            ["TABELA_ID" => 22, "COLUNA_ID" => 2, "DESCRICAO" => "Seleção", "ATIVO" => 1],
            ["TABELA_ID" => 22, "COLUNA_ID" => 3, "DESCRICAO" => "Contratação", "ATIVO" => 1],
            ["TABELA_ID" => 22, "COLUNA_ID" => 4, "DESCRICAO" => "Nomeação", "ATIVO" => 1],
        ];
    }
}
