<?php

namespace App\Rules\Funcionario;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Log;

class FuncionarioAtribuicaoLotacaoObrigatoriaRule implements Rule
{
    public function __construct()
    {
        //
    }

    public function passes($attribute, $value)
    {
        $lotacoes = $value;
        foreach ($lotacoes as $lotacao) {
            if (count($lotacao['atribuicaoLotacoes']) == 0) {
                return false;
            }
        }
        return true;
    }

    public function message()
    {
        return 'Existem <b>LOTAÇÕES</b> sem <b>ATRIBUIÇÃO</b>';
    }
}
