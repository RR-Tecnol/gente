<?php

namespace App\Rules;

use App\Models\Atribuicao;
use App\Models\Funcionario;
use App\Models\Lotacao;
use App\Models\Pessoa;
use Illuminate\Contracts\Validation\Rule;

class FuncionarioEscolariadeAtribuicaoRule implements Rule
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function passes($attribute, $value)
    {
        $atribuicaoId = $value;
        $atribuicao = Atribuicao::find($atribuicaoId);
        $lotacaoId = $this->data['LOTACAO_ID'];
        $lotacao = Lotacao::find($lotacaoId);
        $funcionario = Funcionario::find($lotacao->FUNCIONARIO_ID);
        $pessoa = Pessoa::find($funcionario->PESSOA_ID);
        return !($pessoa->PESSOA_ESCOLARIDADE < $atribuicao->ATRIBUICAO_ESCOLARIDADE);
    }

    public function message()
    {
        return 'Atribuição incompatível com a escolaridade do funcionário';
    }
}
