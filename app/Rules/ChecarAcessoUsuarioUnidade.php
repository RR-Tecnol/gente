<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class ChecarAcessoUsuarioUnidade implements Rule
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
        $usuarioLogado = Auth::user();
        $usuario = $usuarioLogado->whereHas('usuarioUnidades.unidade', function ($query) use ($value) {
            $query->where('UNIDADE_ID', $value);
        })->first();
        dd($usuario);
        return $usuario;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'O usuário só poderá cadastrar ou editar registros de unidades das quais ele possuir acesso.';
    }
}
