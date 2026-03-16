<?php


namespace Database\Seeders\tgs;


class TgMotivoFimDependencia {
    public static function tabela() {
        return [
            ["TABELA_ID" => 12, "COLUNA_ID" => 0, "DESCRICAO" => "MOTIVO_FIM_DEPENDÊNCIA", "ATIVO" => 1],
            ["TABELA_ID" => 12, "COLUNA_ID" => 1, "DESCRICAO" => "À Pedido", "ATIVO" => 1],
            ["TABELA_ID" => 12, "COLUNA_ID" => 2, "DESCRICAO" => "Por Falecimento", "ATIVO" => 1],
            ["TABELA_ID" => 12, "COLUNA_ID" => 3, "DESCRICAO" => "Por Maioridade", "ATIVO" => 1],
        ];
    }
}
