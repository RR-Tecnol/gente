<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class DiferenteRule implements Rule
{
    private $outroCampo;
    private $valor;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($outroCampo, $valor = 1)
    {
        $this->outroCampo = $outroCampo;
        $this->valor = $valor;
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
        return !($value == $this->valor && $this->outroCampo == $this->valor);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Os campos IMPOSTO e SALÁRIO são mutuamente excludentes.';
    }
}
