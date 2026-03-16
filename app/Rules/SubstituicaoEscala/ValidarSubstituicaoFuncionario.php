<?php

namespace App\Rules\SubstituicaoEscala;

use App\Models\DetalheEscalaItem;
use Illuminate\Contracts\Validation\Rule;

class ValidarSubstituicaoFuncionario implements Rule
{
    private $request;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($request)
    {
        $this->request = $request;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $detalheEscalaItem = DetalheEscalaItem::find($this->request['DETALHE_ESCALA_ITEM_ID']);
        return ($detalheEscalaItem->detalheEscala->FUNCIONARIO_ID != $value);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Não pode substituir o mesmo funcionario.';
    }
}
