<?php

namespace App\Http\Requests\SubstituicaoEscala;

use App\Rules\ChecarAcessoUsuarioSetor;
use App\Rules\SubstituicaoEscala\ValidarSubstituicaoAtribuicao;
use App\Rules\SubstituicaoEscala\ValidarSubstituicaoData;
use App\Rules\SubstituicaoEscala\ValidarSubstituicaoFuncionario;
use App\Rules\SubstituicaoEscala\ValidarSubstituicaoProximoDia;
use App\Rules\SubstituicaoEscala\ValidarSubstituicaoSetor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SubstituicaoEscalaCreateRequest extends FormRequest
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

    public function rules()
    {
        $requsicao = $this->request->all();
//        dd($requsicao);
        return [
            "DETALHE_ESCALA_ITEM_ID" => ["required","integer",new ValidarSubstituicaoData($requsicao), new ValidarSubstituicaoProximoDia],
            'detalheEscalaItem.detalheEscala.escala.SETOR_ID' => ['required',new ChecarAcessoUsuarioSetor],
            // "FUNCIONARIO_ID" => ["required","integer",new ValidarSubstituicaoFuncionario($requsicao),new ValidarSubstituicaoSetor($requsicao),new ValidarSubstituicaoAtribuicao($requsicao)],
            "SUBSTITUICAO_ESCALA_JUSTIFICATIVA" => ["required","min:3"],
        ];
    }

    public function attributes()
    {
        return[
            "SUBSTITUICAO_ESCALA_ID" => "<b>SUBSTITUICAO ESCALA ID</b>",
            "FUNCIONARIO_ID" => "<b>FUNCIONARIO</b>",
            "DETALHE_ESCALA_ITEM_ID" => "<b>DATA DA ESCALA</b>",
            "SUBSTITUICAO_ESCALA_JUSTIFICATIVA" => "<b>JUSTIFICATIVA</b>",
        ];
    }
}
