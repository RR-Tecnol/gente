<?php

namespace App\MyLibs;

use App\Models\Audit;

class AuditHelper
{
    public static function saveCreated($auditTabela, $auditLinhaId, $auditDepois)
    {
        $userId = auth()->id();
        $userName = auth()->user()?->USUARIO_NOME;

        dispatch(function () use ($auditTabela, $auditLinhaId, $auditDepois, $userId, $userName) {
            $audit = new Audit();
            $audit->AUDIT_DATA = date('Y-m-d H:i:s');
            $audit->AUDIT_USER_ID = $userId;
            $audit->AUDIT_USER = $userName;
            $audit->AUDIT_TABELA = $auditTabela;
            $audit->AUDIT_LINHA_ID = $auditLinhaId;
            $audit->AUDIT_DEPOIS = is_string($auditDepois) ? $auditDepois : json_encode($auditDepois);
            $audit->AUDIT_OPERACAO = "I";
            $audit->save();
        })->afterResponse();
    }

    public static function saveUpdating($auditTabela, $auditLinhaId, $auditCampo, $auditAntes, $auditDepois)
    {
        $userId = auth()->id();
        $userName = auth()->user()?->USUARIO_NOME;

        dispatch(function () use ($auditTabela, $auditLinhaId, $auditCampo, $auditAntes, $auditDepois, $userId, $userName) {
            $audit = new Audit();
            $audit->AUDIT_DATA = date('Y-m-d H:i:s');
            $audit->AUDIT_USER_ID = $userId;
            $audit->AUDIT_USER = $userName;
            $audit->AUDIT_TABELA = $auditTabela;
            $audit->AUDIT_LINHA_ID = $auditLinhaId;
            $audit->AUDIT_CAMPO = $auditCampo;
            $audit->AUDIT_ANTES = is_string($auditAntes) ? $auditAntes : json_encode($auditAntes);
            $audit->AUDIT_DEPOIS = is_string($auditDepois) ? $auditDepois : json_encode($auditDepois);
            $audit->AUDIT_OPERACAO = "U";
            $audit->save();
        })->afterResponse();
    }

    public static function saveDeleting($auditTabela, $auditLinhaId, $auditAntes)
    {
        $userId = auth()->id();
        $userName = auth()->user()?->USUARIO_NOME;

        dispatch(function () use ($auditTabela, $auditLinhaId, $auditAntes, $userId, $userName) {
            $audit = new Audit();
            $audit->AUDIT_DATA = date('Y-m-d H:i:s');
            $audit->AUDIT_USER_ID = $userId;
            $audit->AUDIT_USER = $userName;
            $audit->AUDIT_TABELA = $auditTabela;
            $audit->AUDIT_LINHA_ID = $auditLinhaId;
            $audit->AUDIT_ANTES = is_string($auditAntes) ? $auditAntes : json_encode($auditAntes);
            $audit->AUDIT_OPERACAO = "D";
            $audit->save();
        })->afterResponse();
    }
}
