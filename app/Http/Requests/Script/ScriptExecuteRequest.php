<?php

namespace App\Http\Requests\Script;

use App\MyLibs\ScritpEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ScriptExecuteRequest extends FormRequest
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
        $script = $this->input('script');

        $rules = [];

        if ($script == ScritpEnum::DELETAR_PESSOA) {
            $rules['cpf'] = ['required', 'string', 'min:11', 'max:11'];
        }

        if ($script == ScritpEnum::DELETAR_ESCALA) {
            $rules['escalaId'] = ['required', 'integer'];
        }

        // Se quiser garantir que "script" sempre venha
        $rules['script'] = ['required', 'in:1,2'];

        return $rules;
    }

    public function attributes()
    {
        return[
            "cpf" => "<b>CPF</b>",
        ];
    }
}
