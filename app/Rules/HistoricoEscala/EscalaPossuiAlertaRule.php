<?php

namespace App\Rules\HistoricoEscala;

use App\Models\DetalheEscala;
use Illuminate\Contracts\Validation\Rule;

class EscalaPossuiAlertaRule implements Rule
{
    private $escala;

    public function __construct($escala)
    {
        $this->escala = $escala;
    }

    public function passes($attribute, $value)
    {
        $retorno = DetalheEscala::where("ESCALA_ID", $this->escala['ESCALA_ID'])
            ->whereHas("detalheEscalaAlertas")
            ->whereDoesntHave("detalheEscalaAutoriza")
            ->count();
        return !($retorno > 0);
    }

    public function message()
    {
        return 'A escala possui alertas e precisa de autorização';
    }
}
