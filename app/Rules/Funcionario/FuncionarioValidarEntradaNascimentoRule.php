<?php

namespace App\Rules\Funcionario;

use App\Models\Pessoa;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Rule;

class FuncionarioValidarEntradaNascimentoRule implements Rule
{
    private $funcionarioDataInicio;

    public function __construct($funcionarioDataInicio)
    {
        $this->funcionarioDataInicio = $funcionarioDataInicio;
    }

    public function passes($attribute, $value)
    {
        $pessoaId = $value;
        $pessoa = Pessoa::find($pessoaId);
        $dn = $pessoa->PESSOA_DATA_NASCIMENTO == null ? null : Carbon::parse($pessoa->PESSOA_DATA_NASCIMENTO);
        $dataInicio = $this->funcionarioDataInicio == null ? null : Carbon::parse($this->funcionarioDataInicio);
        if ($dataInicio < $dn) {
            return false;
        } else {
            return true;
        }
    }

    public function message()
    {
        return 'A <b>DATA INICIAL</b> do funcionário não pode ser menor que a <b>DATA DE NASCIMENTO</b> da pessoa';
    }
}
