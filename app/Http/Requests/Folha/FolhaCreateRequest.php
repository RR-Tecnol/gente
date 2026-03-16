<?php

namespace App\Http\Requests\Folha;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class FolhaCreateRequest extends FormRequest
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
            'FOLHA_DESCRICAO'=>['required'],
            'FOLHA_TIPO'=>['required'],
            'VINCULO_ID'=>['required'],
            'FOLHA_COMPETENCIA'=>['required'],
            'setores'=>['required'],
        ];
    }

    public function attributes()
    {
        return [
            'FOLHA_ID' => '<b>ID</b>',
            'FOLHA_DESCRICAO' => '<b>DESCRIÇÃO</b>',
            'FOLHA_TIPO' => '<b>TIPO</b>',
            'VINCULO_ID' => '<b>VINCULO</b>',
            'FOLHA_COMPETENCIA' => '<b>VALOR COMPETENCIA</b>',
            'FOLHA_QTD_SERVIDORES' => '<b>QUANTIDADE SERVIDORES</b>',
            'FOLHA_VALOR_TOTAL' => '<b>VALOR TOTAL</b>',
            'FOLHA_ARQUIVO' => '<b>ARQUIVO</b>',
            'FOLHA_CHECKSUM' => '<b>CHECKSUM</b>',
            'setores' => '<b>SETORES</b>'
        ];
    }
}
