<?php

namespace App\Http\Requests\LotacaoEvento;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class LotacaoEventoSearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "VINCULO_ID" => ["required", "integer"],
        ];
    }

    public function attributes()
    {
        return[
            "VINCULO_ID" => "<b>VÍNCULO</b>",
        ];
    }
}
