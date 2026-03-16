<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Termo extends Model
{
    use HasFactory;

    protected $table = "TERMO";
    protected  $primaryKey = "TERMO_ID";
    public $timestamps = false;
    protected $fillable = [
        "TERMO_NOME",
        "TERMO_ARQUIVO",
        "TERMO_EXTENSAO",
        "TERMO_ATIVO",
    ];

    protected $casts = [
        'TERMO_ATIVO' => 'integer'
    ];

    public function termoUsuario(){
        return $this->hasMany(TermoUsuario::class,'TERMO_ID','TERMO_ID');
    }
}
