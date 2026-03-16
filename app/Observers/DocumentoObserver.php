<?php

namespace App\Observers;

use App\Models\Documento;
use App\MyLibs\TipoDocumentoEnum;
use App\MyLibs\Utilitarios;

class DocumentoObserver extends BaseAuditObserver
{
    public function creating(Documento $documento)
    {
        if ($documento->TIPO_DOCUMENTO_ID === TipoDocumentoEnum::CPF) {
            $documento->DOCUMENTO_NUMERO = Utilitarios::removerCaracteresEspeciaisCpf($documento->DOCUMENTO_NUMERO);
        }
    }

    public function updating($documento)
    {
        if ($documento instanceof Documento && $documento->TIPO_DOCUMENTO_ID === TipoDocumentoEnum::CPF) {
            $documento->DOCUMENTO_NUMERO = Utilitarios::removerCaracteresEspeciaisCpf($documento->DOCUMENTO_NUMERO);
        }

        parent::updating($documento);
    }
}
