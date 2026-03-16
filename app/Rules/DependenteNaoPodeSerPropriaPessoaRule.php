<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class DependenteNaoPodeSerPropriaPessoaRule implements Rule
{
    private $pessoaId;

    public function __construct($pessoaId)
    {
        $this->pessoaId = $pessoaId;
    }

    public function passes($attribute, $value)
    {
        $dependentePessoaId = $value;
        return !($this->pessoaId == $dependentePessoaId);
    }

    public function message()
    {
        return 'O <b>DEPENDENTE</b> não pode ser a própria pessoa';
    }
}
