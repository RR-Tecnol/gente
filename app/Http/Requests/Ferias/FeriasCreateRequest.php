<?php

namespace App\Http\Requests\Ferias;

use App\Models\Funcionario;
use App\Models\Lotacao;
use App\Rules\Afastamento\ChecarRepeticaoPeriodoAfastamento;
use App\Rules\ChecarAcessoUsuarioSetor;
use App\Rules\ChecarFeriado;
use App\Rules\ChecarFimDeSemana;
use App\Rules\Ferias\ChecarFuncionarioFeriasPorLotacao;
use App\Rules\Ferias\ChecarRepeticaoPeriodoAquisitivoFerias;
use App\Rules\Ferias\ChecarRepeticaoPeriodoFerias;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class FeriasCreateRequest extends FormRequest
{
    private $funcionario;
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
        $this->funcionario = Funcionario::find($this->request->all()['FUNCIONARIO_ID']);
        
        return [
            // "PESSOA_NOME" => ['required'],
            "FUNCIONARIO_ID" => ['required',"integer"],
            "FERIAS_DATA_INICIO" => ['required', 'before:FERIAS_DATA_FIM',"date",
                                        new ChecarRepeticaoPeriodoFerias($this->request->all()['FUNCIONARIO_ID']),
                                        new ChecarRepeticaoPeriodoAfastamento($this->request->all()['FUNCIONARIO_ID'],null,
                                            'O servidor selecionado encontra-se afastado no período informado.'),
                                            "after_or_equal:". date('Y-m-d',strtotime(date('Y-m-d').'+ 2 months')),
                                            new ChecarFuncionarioFeriasPorLotacao($this->request->all()['FUNCIONARIO_ID']),
                                            new ChecarFimDeSemana,
                                            new ChecarFeriado,
                                        ],
            "FERIAS_DATA_FIM" => ['required',"date",
                                        new ChecarRepeticaoPeriodoFerias($this->request->all()['FUNCIONARIO_ID']),
                                        new ChecarRepeticaoPeriodoAfastamento($this->request->all()['FUNCIONARIO_ID'], null,
                                            'O servidor selecionado encontra-se afastado no período informado.')],
            "FERIAS_AQUISITIVO_INICIO" => ['required','integer','digits:4','min:'.($this->dataInicioMinimo()-2),'max:'.($this->dataInicioMinimo()-1),
                                        new ChecarRepeticaoPeriodoAquisitivoFerias($this->request->all()['FUNCIONARIO_ID'])],
            "FERIAS_AQUISITIVO_FIM" => ['required','integer','required_with:FERIAS_AQUISITIVO_INICIO',
                                        'gt:FERIAS_AQUISITIVO_INICIO','digits:4','max:'.($this->request->all()['FERIAS_AQUISITIVO_INICIO']+1),
                                        new ChecarRepeticaoPeriodoAquisitivoFerias($this->request->all()['FUNCIONARIO_ID'])],
            'funcionario.lotacoes.*.SETOR_ID' => [new ChecarAcessoUsuarioSetor],
        ];
    }

    protected function dataInicioMinimo(){
        $data_atual = date('Y-m-d');
        $ano_atual = date('Y');
        if($this->funcionario == null){
            return $ano_atual;
        }
        $funcionario = $this->funcionario;
        $data_funcionario = date("$ano_atual".'-m-d',strtotime($funcionario->FUNCIONARIO_DATA_INICIO));
        if($data_atual >= $data_funcionario){
            return $ano_atual;
        }else{
            return $ano_atual - 1;
        }
    }

    public function attributes()
    {
        return[
            "PESSOA_NOME" => "<b>PESSOA</b>",
            "FERIAS_ID" => "<b>ID FÉRIAS</b>",
            "FUNCIONARIO_ID" => "<b>FUNCIONARIO</b>",
            "FERIAS_DATA_INICIO" => "<b>DATA INÍCIO FÉRIAS</b>",
            "FERIAS_DATA_FIM" => "<b>DATA FIM</b>",
            "FERIAS_AQUISITIVO_INICIO" => "<b>AQUISIÇÃO INÍCIO</b>",
            "FERIAS_AQUISITIVO_FIM" => "<b>AQUISIÇÃO FIM</b>",
        ];
    }

    public function messages()
    {
        return [
          "FERIAS_DATA_INICIO.after_or_equal" => "A solicitação de férias deve ser feita com 2 meses de antecedência.",
          "FERIAS_AQUISITIVO_INICIO.digits" => "O campo :attribute deve ter o formato AAAA.",
          "FERIAS_AQUISITIVO_INICIO.min" => "O campo :attribute deve ser maior ou igual ao ano de :min.",
          "FERIAS_AQUISITIVO_INICIO.max" => "Período aquisitivo ainda não disponível.",
          "FERIAS_AQUISITIVO_FIM.digits" => "O campo :attribute deve ter o formato AAAA.",
          "FERIAS_AQUISITIVO_FIM.max" => "O campo :attribute não deve ser maior que o ano de :max.",
        ];
    }
}
