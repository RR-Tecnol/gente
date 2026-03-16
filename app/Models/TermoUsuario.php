<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TermoUsuario extends Model
{
    use HasFactory;
    
    protected $table = "TERMO_USUARIO";
    protected  $primaryKey = "TERMO_USUARIO_ID";
    public $timestamps = false;
    protected $fillable = [
        "USUARIO_ID",
        "TERMO_ID",
        "TERMO_USUARIO_DATA",
    ];
}
