<?php

namespace App\Http\Requests\EventoVinculo;

use App\Http\Requests\evento\EventoCreateRequest;
use Illuminate\Foundation\Http\FormRequest;

class EventoVinculoDeleteRequest extends EventoVinculoCreateRequest
{
    

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'EVENTO_VINCULO_ID' => ['required']
        ];
    }
}
