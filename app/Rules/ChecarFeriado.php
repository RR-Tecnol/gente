<?php

namespace App\Rules;

use App\Models\Feriado;
use Illuminate\Contracts\Validation\Rule;

class ChecarFeriado implements Rule
{
    private $msg;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
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
        $feriado = Feriado::where('FERIADO_DATA', $value)->first();

        if ($feriado) {
            $this->msg = "O campo :attribute coincide com o feriado: <b>$feriado->FERIADO_DESCRICAO</b>";
            return false;
        }
        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->msg;
    }
}
