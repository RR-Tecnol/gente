<?php

namespace App\Rules;

use App\Models\AtribuicaoConfig;
use Illuminate\Contracts\Validation\Rule;

class CargaHorariaAtribuicaoPorteUnidadeRule implements Rule
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function passes($attribute, $value)
    {
        $atribuicaoId = $value;

        $atribuicaoConfig = AtribuicaoConfig::with([])
            ->where("ATRIBUICAO_CONFIG_CARGA_HORARIA", $this->data['ATRIBUICAO_LOTACAO_CARGA_HORARIA'])
            ->where("ATRIBUICAO_CONFIG_PORTE_UNIDADE", $this->data['UNIDADE_PORTE'])
            ->where("ATRIBUICAO_ID", $atribuicaoId)
            ->get();
        return !($atribuicaoConfig->count() == 0);
    }

    public function message()
    {
        return 'Não há configurações cadastradas para esse perfil de funcionário';
    }
}
