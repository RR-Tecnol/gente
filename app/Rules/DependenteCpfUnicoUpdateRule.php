<?php

namespace App\Rules;

use App\Models\Documento;
use App\MyLibs\TipoDocumentoEnum;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Log;

class DependenteCpfUnicoUpdateRule implements Rule
{
    private $pessoaId;

    public function __construct($pessoaId)
    {
        $this->pessoaId = $pessoaId;
    }

    public function passes($attribute, $value)
    {
        $cpf = $value;
        $retorno = Documento::with(Documento::$relacionamentos)
            ->where("TIPO_DOCUMENTO_ID", TipoDocumentoEnum::CPF)
            ->where("DOCUMENTO_NUMERO", $cpf)
            ->where("PESSOA_ID", "!=", $this->pessoaId)
            ->count();
        return !($retorno > 0);
    }

    public function message()
    {
        return 'O <b>CPF</b> informado já está cadastrado';
    }
}
