<?php

namespace App\Rules\PessoaBanco;

use App\Models\Banco;
use Illuminate\Contracts\Validation\Rule;

class ContaBancoOficialExisteRule implements Rule
{
    public function __construct()
    {
        //
    }

    public function passes($attribute, $value)
    {
        $pessoaBancos = $value;
        return $this->existeContaOficial($pessoaBancos);
    }

    public function message()
    {
        return 'Informe ao menos uma conta em um <b>BANCO</b> oficial';
    }

    private function existeContaOficial($pessoaBancos)
    {
        $a = array_filter($pessoaBancos, function ($row) {
            $banco = Banco::find($row['BANCO_ID']);
            return $banco->BANCO_OFICIAL == 1;
        });
        return count($a) > 0;
    }
}
