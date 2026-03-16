<?php

namespace App\Rules\DetalheEscalaItem;

use App\Models\Escala;
use Illuminate\Contracts\Validation\Rule;

class ChecarDataComPeriodoEscala implements Rule
{
    private $request;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($request)
    {
        $this->request = $request;
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
        $escala = Escala::find($this->request['ESCALA_ID']);
        $per = explode('/', $escala->ESCALA_COMPETENCIA);
        $periodo = [
            'INICIO' => date('Y-m-01', strtotime("$per[1]-$per[0]")),
            'FIM' => date('Y-m-t', strtotime("$per[1]-$per[0]")),
        ];

        if ($periodo['INICIO'] <= $value && $periodo['FIM'] >= $value) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Só pode colocar datas no periodo da Escala.';
    }
}
