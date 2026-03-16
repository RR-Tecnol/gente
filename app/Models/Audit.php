<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *@property string AUDIT_DATA
 *@property integer AUDIT_USER_ID
 *@property string AUDIT_USER
 *@property string AUDIT_TABELA
 *@property integer AUDIT_LINHA_ID
 *@property string AUDIT_CAMPO
 *@property string AUDIT_ANTES
 *@property string AUDIT_DEPOIS
 *@property string AUDIT_OPERACAO
 */
class Audit extends Model
{
    protected $table = "AUDIT";
    protected $primaryKey = "AUDIT_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
}
