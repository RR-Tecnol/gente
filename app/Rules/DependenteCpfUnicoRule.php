<?php

namespace App\Rules;

use App\Models\Documento;
use App\MyLibs\TipoDocumentoEnum;
use Illuminate\Contracts\Validation\Rule;

class DependenteCpfUnicoRule implements Rule
{
    public function __construct() {}

    public function passes($attribute, $value)
    {
        $cpf = $value;
        $retorno = Documento::with(Documento::$relacionamentos)
            ->where("TIPO_DOCUMENTO_ID", TipoDocumentoEnum::CPF)
            ->where("DOCUMENTO_NUMERO", $cpf)
            ->count();
        return !($retorno > 0);
    }

    public function message()
    {
        return 'O <b>CPF</b> informado já está cadastrado';
    }
}
