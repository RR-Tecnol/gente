<?php

namespace App\Rules\PessoaProfissao;

use App\Models\PessoaProfissao;
use Illuminate\Contracts\Validation\Rule;

class ChecarPessoaProfissaoFilho implements Rule
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
        $pessoaProfissaoFilho = PessoaProfissao::where('PESSOA_ID', $value)->count();
        return $pessoaProfissaoFilho > 0 ? false : true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Existem <b>PROFISSÕES</b> associadas a esta <b>PESSOA</b>.';
    }
}
