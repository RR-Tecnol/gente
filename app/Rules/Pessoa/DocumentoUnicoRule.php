<?php

namespace App\Rules\Pessoa;

use App\Models\Documento;
use App\MyLibs\TipoDocumentoEnum;
use Illuminate\Contracts\Validation\Rule;

class DocumentoUnicoRule implements Rule
{
    private $duplicados = [];
    public function __construct() {}

    public function passes($attribute, $value)
    {
        $documentos = $value;

        foreach ($documentos as $doc) {
            $retorno = Documento::with(["tipoDocumento", "pessoa"])
                ->where("TIPO_DOCUMENTO_ID", $doc['TIPO_DOCUMENTO_ID'])
                ->where("DOCUMENTO_NUMERO", $doc['DOCUMENTO_NUMERO'])
                ->first();
            if ($retorno) {
                $this->duplicados[] = $retorno;
            }
        }

        return !(count($this->duplicados) > 0);
    }

    public function message()
    {
        $msg = "Os seguintes <b>DOCUMENTOS</b> já estão cadastrados:<br>";
        foreach ($this->duplicados as $documento) {
            $msg .= "- <b>{$documento->tipoDocumento->TIPO_DOCUMENTO_DESCRICAO}: </b>{$documento->DOCUMENTO_NUMERO}, cadastrado em nome de <b>{$documento->pessoa->PESSOA_NOME}</b><br>";
        }
        return $msg;
    }
}
