<?php

namespace App\Rules\Usuario;

use App\Models\UsuarioPerfil;
use Illuminate\Contracts\Validation\Rule;

class UnicoUsuarioPerfil implements Rule
{
    private $usuarioId;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($usuarioId)
    {
        $this->usuarioId = $usuarioId;
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
        $unico = UsuarioPerfil::where('USUARIO_ID', $this->usuarioId)
            ->where('PERFIL_ID', $value)
            ->first();

        return $unico ? false : true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Este usuário já possui esse perfil vinculado a ele.';
    }
}
