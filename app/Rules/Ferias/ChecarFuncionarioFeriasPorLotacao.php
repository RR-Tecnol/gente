<?php

namespace App\Rules\Ferias;

use App\Models\Funcionario;
use App\Models\Lotacao;
use Illuminate\Contracts\Validation\Rule;

class ChecarFuncionarioFeriasPorLotacao implements Rule
{
    private $msg;
    private $funcionarioId;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($funcionarioId)
    {
        $this->funcionarioId = $funcionarioId;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if ($this->funcionarioId == null) {
            $this->msg = "Funcionario não foi selecionada.";
            return false;
        }
        $funcionario = Funcionario::find($this->funcionarioId);

        $dataFerias = date('Y-m-d', strtotime($funcionario->FUNCIONARIO_DATA_INICIO . "+ 1 year"));
        return ($dataFerias >= $value) ? false : true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->msg ?: "O Funcionário ainda não completou o período aquisitivo.";
    }
}
