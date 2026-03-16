<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsuarioSetor extends Model
{
    use HasFactory;
    protected $table = 'USUARIO_SETOR';
    protected $primaryKey = 'USUARIO_SETOR_ID';
    public $timestamps = false;
    protected $fillable = [
        'SETOR_ID',
        'USUARIO_ID',
        'ATIVO',
    ];

    protected $casts = [
        'USUARIO_SETOR_ID' => 'integer',
        'SETOR_ID' => 'integer',
        'USUARIO_ID' => 'integer',
        'ATIVO' => 'integer',
    ];

    public function setor()
    {
        return $this->belongsTo(Setor::class, 'SETOR_ID', 'SETOR_ID');
    }
}
