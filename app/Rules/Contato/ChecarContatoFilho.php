<?php

namespace App\Rules\Contato;

use App\Models\Contato;
use Illuminate\Contracts\Validation\Rule;

class ChecarContatoFilho implements Rule
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
        $contatoFilho = Contato::where('PESSOA_ID', $value)->count();
        return $contatoFilho > 0 ? false : true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Existem <b>CONTATOS</b> associadas a esta <b>PESSOA</b>.';
    }
}
