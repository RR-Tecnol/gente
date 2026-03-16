<?php

namespace App\Rules\SubstituicaoEscala;

use App\Models\DetalheEscalaItem;
use Illuminate\Contracts\Validation\Rule;

class ValidarSubstituicaoData implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct() {}

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $detalheEsalaItem = DetalheEscalaItem::find($value);
        return ($detalheEsalaItem->DETALHE_ESCALA_ITEM_DATA > date('Y-m-d'));
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Só poderão ser feitas substituições em datas posteriores a atual.';
    }
}
