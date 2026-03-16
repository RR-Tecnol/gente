<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FonteRecurso extends Model
{
    protected $table = "FONTE_RECURSO";
    protected $primaryKey = "FONTE_RECURSO_ID";
    public $timestamps = false;
    protected $fillable = [
        "DESCRICAO",
    ];
}
