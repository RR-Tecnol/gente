<?php

namespace App\Rules\Pessoa;

use App\MyLibs\TipoDocumentoEnum;
use App\MyLibs\ValidadorDocumento;
use Illuminate\Contracts\Validation\Rule;

class CpfValidoRule implements Rule
{
    public function __construct()
    {
        //
    }

    public function passes($attribute, $value)
    {
        $documentos = $value;
        $documentoCPF = $this->getCpf($documentos);
        return ValidadorDocumento::validaCPF($documentoCPF['DOCUMENTO_NUMERO']);
    }

    private function getCpf($documentos)
    {
        foreach ($documentos as $doc) {
            if ($doc['TIPO_DOCUMENTO_ID'] === TipoDocumentoEnum::CPF) {
                return $doc;
            }
        }
        return null;
    }

    public function message()
    {
        return 'O <b>CPF</b> informado é inválido';
    }
}
