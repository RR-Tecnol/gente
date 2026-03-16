<?php

namespace App\Observers;

class HistoricoEscalaObserver extends BaseAuditObserver
{
    public function creating($historicoEscala)
    {
        $historicoEscala::where("ESCALA_ID", $historicoEscala->ESCALA_ID)
            ->update(["HISTORICO_ESCALA_ULTIMO" => null]);

        $historicoEscala->HISTORICO_ESCALA_ULTIMO = 1;
    }
}
