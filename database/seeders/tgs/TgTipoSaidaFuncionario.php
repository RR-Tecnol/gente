<?php


namespace Database\Seeders\tgs;


class TgTipoSaidaFuncionario {
    public static function tabela() {
        return [
            ["TABELA_ID" => 23, "COLUNA_ID" => 0, "DESCRICAO" => "TIPO_SAIDA_FUNCIONARIO", "ATIVO" => 1],
            ["TABELA_ID" => 23, "COLUNA_ID" => 1, "DESCRICAO" => "APOSENTADORIA", "ATIVO" => 1],
            ["TABELA_ID" => 23, "COLUNA_ID" => 2, "DESCRICAO" => "FALECIMENTO", "ATIVO" => 1],
            ["TABELA_ID" => 23, "COLUNA_ID" => 3, "DESCRICAO" => "EXONERAÇÃO", "ATIVO" => 1],
        ];
    }
}
