<?php

namespace App\Rules\Dependente;

use App\Models\Pessoa;
use Illuminate\Contracts\Validation\Rule;

class ValidarDatasDependente implements Rule
{
    private $msg = [];
    public function __construct()
    {
        //
    }

    public function passes($attribute, $value)
    {
        $dependentes = $value;
        foreach ($dependentes as $dependente) {
            $pessoa = Pessoa::find($dependente['DEPENDENTE_PESSOA_ID']);
            if (($dependente['DEPENDENTE_DT_FIM'] <= $dependente['DEPENDENTE_DT_INICIO']) && $dependente['DEPENDENTE_DT_FIM'] != null) {
                $this->msg[] = "A <b>DATA FINAL</b> do dependente <b>{$pessoa->PESSOA_NOME}</b> deve ser maior que a <b>DATA INICIAL</b>";
            }
            if ($dependente['DEPENDENTE_DT_FIM'] && !$dependente['DEPENDENTE_TIPO_FIM']) {
                $this->msg[] = "A <b>TIPO DE FINALIZAÇÃO</b> do dependente <b>{$pessoa->PESSOA_NOME}</b> deve ser informado";
            }
        }
        return !(count($this->msg) > 0);
    }

    public function message()
    {
        $msg = "<b>DEPENDENTES:</b></br>";
        foreach ($this->msg as $row) {
            $msg .= "$row </br>";
        }
        return $msg;
    }
}
