<?php

namespace App\Rules\Documento;

use App\Models\Documento;
use Illuminate\Contracts\Validation\Rule;

class ChecarDocumentoFilho implements Rule
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
        $documentoFilho = Documento::where('PESSOA_ID', $value)->count();
        return $documentoFilho > 0 ? false : true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Existem <b>DOCUMENTOS</b> associadas a esta <b>PESSOA</b>.';
    }
}
