<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int STATUS_ESCALA_ID
 * @property string STATUS_ESCALA_DESCRICAO
 * @property int STATUS_ESCALA_ATIVA
 *
 */

class StatusEscala extends Model
{
    protected $table = "STATUS_ESCALA";
    protected $primaryKey = "STATUS_ESCALA_ID";
    public $timestamps = false;
    protected $fillable = [
        "STATUS_ESCALA_DESCRICAO",
        "STATUS_ESCALA_ATIVA",
    ];
}
