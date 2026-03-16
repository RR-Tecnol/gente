<?php

namespace App\Http\Requests\Ferias;

use App\Rules\Ferias\ChecarRepeticaoPeriodoAquisitivoFerias;
use App\Rules\Ferias\ChecarRepeticaoPeriodoFerias;

class FeriasUpdateRequest extends FeriasCreateRequest
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
        $regras["FERIAS_ID"] = ["required","integer"];
        $regras["FERIAS_DATA_INICIO"] = ['required', 'before:FERIAS_DATA_FIM',"date", new ChecarRepeticaoPeriodoFerias($request['FUNCIONARIO_ID'],$request['FERIAS_ID'])];
        $regras["FERIAS_DATA_FIM"] = ['required',"date", new ChecarRepeticaoPeriodoFerias($request['FUNCIONARIO_ID'],$request['FERIAS_ID'])];
        $regras["FERIAS_AQUISITIVO_INICIO"]= ['required','integer','digits:4','min:'.($this->dataInicioMinimo()-2),'max:'.($this->dataInicioMinimo()-1),
                                        new ChecarRepeticaoPeriodoAquisitivoFerias($this->request->all()['FUNCIONARIO_ID'],$this->request->all()['FERIAS_ID'])];
        $regras["FERIAS_AQUISITIVO_FIM"] = ['required','integer','required_with:FERIAS_AQUISITIVO_INICIO',
                                        'gt:FERIAS_AQUISITIVO_INICIO','digits:4','max:'.($this->request->all()['FERIAS_AQUISITIVO_INICIO']+1),
                                        new ChecarRepeticaoPeriodoAquisitivoFerias($this->request->all()['FUNCIONARIO_ID'],$this->request->all()['FERIAS_ID'])];
        return $regras;
    }
}
