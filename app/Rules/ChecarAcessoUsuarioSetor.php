<?php

namespace App\Rules;

use App\Models\Usuario;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class ChecarAcessoUsuarioSetor implements Rule
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
        $usuario = Usuario::where('USUARIO_ID', Auth::id())
            ->whereHas('usuarioUnidades', function ($query) use ($value) {
                $query->where('USUARIO_UNIDADE_ATIVO', 1)
                    ->whereHas('unidade.setores', function ($query) use ($value) {
                        $query->where('SETOR_ID', $value);
                    });
            })

            ->first();
        return $usuario;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'O usuário só poderá cadastrar ou editar escalas dos setores para os quais ele possuir acesso.';
    }
}
