<?php

namespace App\Observers;

use Illuminate\Support\Facades\Auth;

class FuncionarioObserver extends BaseAuditObserver
{
    public function creating($funcionario)
    {
        $funcionario->FUNCIONARIO_DATA_CADASTRO = date("Y-m-d H:i:s");
        $funcionario->USUARIO_ID = Auth::id();
    }

    public function updating($funcionario)
    {
        $funcionario->FUNCIONARIO_DATA_ATUALIZACAO = date("Y-m-d H:i:s");

        parent::updating($funcionario);
    }
}
