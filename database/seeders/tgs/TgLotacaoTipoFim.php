<?php


namespace Database\Seeders\tgs;


class TgLotacaoTipoFim {
    public static function tabela() {
        return [
            ["TABELA_ID" => 26, "COLUNA_ID" => 0, "DESCRICAO" => "LOTACAO_TIPO_FIM", "ATIVO" => 1],
            ["TABELA_ID" => 26, "COLUNA_ID" => 1, "DESCRICAO" => "Aposentadoria", "ATIVO" => 1],
            ["TABELA_ID" => 26, "COLUNA_ID" => 2, "DESCRICAO" => "Falecimento", "ATIVO" => 1],
            ["TABELA_ID" => 26, "COLUNA_ID" => 3, "DESCRICAO" => "Abandono", "ATIVO" => 1],
            ["TABELA_ID" => 26, "COLUNA_ID" => 4, "DESCRICAO" => "Exoneração", "ATIVO" => 1],
            ["TABELA_ID" => 26, "COLUNA_ID" => 5, "DESCRICAO" => "Á pedido", "ATIVO" => 1],
            ["TABELA_ID" => 26, "COLUNA_ID" => 6, "DESCRICAO" => "Devolução", "ATIVO" => 1],
        ];
    }
}
