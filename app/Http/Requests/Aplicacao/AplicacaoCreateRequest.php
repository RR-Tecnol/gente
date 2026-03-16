<?php

namespace App\Http\Requests\Aplicacao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AplicacaoCreateRequest extends FormRequest
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
            'APLICACAO_NOME' => ['required','max:50'],
            'APLICACAO_ICONE' => ['max:30'],
            'APLICACAO_URL' => ['max:50'],
            'APLICACAO_GESTAO' => ['required'],
            'APLICACAO_ATIVA' => ['required'],
            'APLICACAO_ORDEM' => ['required'],
            // 'APLICACAO_PAI_ID' => ['required'],
        ];
    }

    public function attributes()
    {
        return [
            'APLICACAO_NOME' => '<b>NOME DA APLICAÇÃO</b>',
            'APLICACAO_ICONE' => '<b>ICONE</b>',
            'APLICACAO_URL' => '<b>URL</b>',
            'APLICACAO_GESTAO' => '<b>GESTÃO</b>',
            'APLICACAO_ATIVA' => '<b>ATIVA</b>',
            'APLICACAO_ORDEM' => '<b>ORDEM</b>',
            'APLICACAO_PAI_ID' => '<b>APLICAÇÃO PAI</b>',
        ];
    }
}
