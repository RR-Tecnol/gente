<?php

namespace App\Http\Requests\AbonoFalta;

use App\Rules\AnexoAbonoFalta\ChecarAnexoAbonoFaltaFilho;

class AbonoFaltaDeleteRequest extends AbonoFaltaCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'id' => [ 'required', 'int', new ChecarAnexoAbonoFaltaFilho ]
        ];
    }
}
