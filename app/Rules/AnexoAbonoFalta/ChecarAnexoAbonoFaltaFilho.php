<?php

namespace App\Rules\AnexoAbonoFalta;

use App\Models\AnexoAbonoFalta;
use Illuminate\Contracts\Validation\Rule;

class ChecarAnexoAbonoFaltaFilho implements Rule
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
        $anexoAbonoFaltaFilhos = AnexoAbonoFalta::where('DETALHE_ESCALA_ITEM_ID', $value)->count();
        return $anexoAbonoFaltaFilhos > 0 ? false : true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Existem <b>ANEXOS</b> associadas a este <b>ABONO FALTA</b>.';
    }
}
