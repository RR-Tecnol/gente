<?php

namespace App\Rules\Documento;

use App\MyLibs\TipoDocumentoEnum;
use App\MyLibs\ValidadorDocumento;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Log;

class CpfValidoRule implements Rule
{
    private $tipoDocumentoId;

    public function __construct($tipoDocumentoId)
    {
        $this->tipoDocumentoId = $tipoDocumentoId;
    }

    public function passes($attribute, $value)
    {
        if ($this->tipoDocumentoId == TipoDocumentoEnum::CPF) {
            return ValidadorDocumento::validaCPF($value);
        }
        return true;
    }

    public function message()
    {
        return 'O <b>CPF</b> informado é inválido';
    }
}
