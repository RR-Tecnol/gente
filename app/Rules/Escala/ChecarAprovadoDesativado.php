<?php

namespace App\Rules\Escala;

use App\Models\Escala;
use Illuminate\Contracts\Validation\Rule;

class ChecarAprovadoDesativado implements Rule
{
    private $message;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct() {}

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $escala = Escala::find($value);

        if ($escala->ESCALA_STATUS == 4) {
            $this->message = 'aprovada';
            return false;
        } else if ($escala->ESCALA_STATUS == 7) {
            $this->message = 'desativada';
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
        return "A escala já está $this->message e não pode ser";
    }
}
