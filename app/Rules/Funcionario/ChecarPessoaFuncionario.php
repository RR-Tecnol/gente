<?php

namespace App\Rules\Funcionario;

use App\Models\Funcionario;
use Illuminate\Contracts\Validation\Rule;

class ChecarPessoaFuncionario implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $pessoaFuncionario = Funcionario::where('PESSOA_ID', $value)->count();
        return $pessoaFuncionario > 0 ? false : true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Esta <b>PESSOA</b> está vinculada a um <b>FUNCIONÁRIO</b>.';
    }
}
