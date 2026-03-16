<?php

namespace App\Rules\Funcionario;

use App\Models\Usuario;
use Illuminate\Contracts\Validation\Rule;

class ChecarFuncionarioUsuario implements Rule
{
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
        $funcionario = Usuario::where('FUNCIONARIO_ID', $value)->count();
        return $funcionario > 0 ? false : true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Funcionario ativo, não pode ser deletado.';
    }
}
