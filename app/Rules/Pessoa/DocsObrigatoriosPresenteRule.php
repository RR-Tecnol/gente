<?php

namespace App\Rules\Pessoa;

use App\Models\TipoDocumento;
use Illuminate\Contracts\Validation\Rule;

class DocsObrigatoriosPresenteRule implements Rule
{
    private $faltantes = [];

    public function __construct()
    {
        //
    }

    public function passes($attribute, $value)
    {
        $documentos = $value;
        $tiposDocumentosObrigatorios = TipoDocumento::with([])->where('TIPO_DOCUMENTO_OBRIGATORIO', 1)->get();
        $this->faltantes = $this->getTiposFaltantes($documentos, $tiposDocumentosObrigatorios);
        return !(count($this->faltantes) > 0);
    }

    public function message()
    {
        $msg = "<b>ABA DOCUMENTOS:</b><br>";
        $msg .= "Os seguintes <b>DOCUMENTOS OBRIGATÓRIOS</b> não foram informados: <br>";
        foreach ($this->faltantes as $faltante) {
            $msg .= "<b>" . $faltante["TIPO_DOCUMENTO_DESCRICAO"] . "</b>, ";
        }
        $msg = substr($msg, 0, -2);
        return $msg;
    }

    private function getTiposFaltantes($documentosInformados, $tiposDocumentosObrigatorios)
    {
        $retorno = [];
        foreach ($tiposDocumentosObrigatorios as $tipoDocumento) {
            if ($this->existe($tipoDocumento->TIPO_DOCUMENTO_ID, $documentosInformados) == false) {
                $retorno[] = $tipoDocumento;
            }
        }
        return $retorno;
    }

    private function existe($tipoDocumentoId, $documentosInformados)
    {
        foreach ($documentosInformados as $documentosInformado) {
            if ($tipoDocumentoId == $documentosInformado["TIPO_DOCUMENTO_ID"]) {
                return true;
            }
        }
        return false;
    }

    private function converter($array)
    {
        $retorno = [];
        foreach ($array as $row) {
            $retorno[] = json_decode($row, true);
        }
        return $retorno;
    }
}
