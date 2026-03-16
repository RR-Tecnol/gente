<?php

namespace App\Http\Requests\Afastamento;

use App\Rules\Afastamento\ChecarRepeticaoPeriodoAfastamento;

class AfastamentoUpdateRequest extends AfastamentoCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $request = $this->request->all();
        $regras = parent::rules();
        $regras["AFASTAMENTO_ID"] = ["required","integer"];
        $regras["PESSOA_NOME"] = ["nullable"];
        $regras["AFASTAMENTO_DATA_INICIO"] = ['required', 'before:AFASTAMENTO_DATA_FIM',"date", new ChecarRepeticaoPeriodoAfastamento($request['LOTACAO_ID'],$request['AFASTAMENTO_ID'])];
        $regras["AFASTAMENTO_DATA_FIM"] = ['required',"date", new ChecarRepeticaoPeriodoAfastamento($request['LOTACAO_ID'],$request['AFASTAMENTO_ID'])];
        return $regras;
    }
}
