<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comentario extends Model
{
    use HasFactory;
    protected $table = 'COMENTARIO';
    protected $primaryKey = 'COMENTARIO_ID';
    public $timestamps = false;
    protected $fillable = [
        'COMENTARIO_DESCRICAO',
        'COMENTARIO_DATA_CRIACAO',
        'COMENTARIO_DATA_CONCLUSAO',
        'USUARIO_ID',
        'PESSOA_ID',
    ];

    public function usuario()
    {
        return $this->hasOne(Usuario::class, 'USUARIO_ID', 'USUARIO_ID');
    }

    public function pessoa()
    {
        return $this->hasOne(Usuario::class, 'PESSOA_ID', 'PESSOA_ID');
    }
}
