<?php

namespace App\Http\Requests\ParametroFinanceiro;

use Illuminate\Validation\Rule;

class ParametroFinanceiroUpdateRequest extends ParametroFinanceiroCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $uniqueIgnoreId = Rule::unique('PARAMETRO_FINANCEIRO')->ignore($this->request->all()["PARAMETRO_FINANCEIRO_ID"], "PARAMETRO_FINANCEIRO_ID");
        $regras = parent::rules();
        $regras["PARAMETRO_FINANCEIRO_NOME"] = ["required", $uniqueIgnoreId];
        return $regras;
    }
}
