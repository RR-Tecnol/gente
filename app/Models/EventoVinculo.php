<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventoVinculo extends Model
{
    use HasFactory;
    protected $table = "EVENTO_VINCULO";
    protected $primaryKey = "EVENTO_VINCULO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        'EVENTO_ID',
        'VINCULO_ID',
        'EVENTO_VINCULO_PROIBIDO',
    ];

    protected $casts = [
        'EVENTO_ID' => 'integer',
        'VINCULO_ID' => 'integer',
        'EVENTO_VINCULO_PROIBIDO' => 'integer',
    ];

    public function vinculo()
    {
        return $this->hasOne(Vinculo::class, 'VINCULO_ID', 'VINCULO_ID');
    }

    public function evento()
    {
        return $this->hasOne(Vinculo::class, 'EVENTO_ID', 'EVENTO_ID');
    }

    public static $relacionamento = [
        'vinculo',
    ];

    public static function buscar($id)
    {
        return self::with(self::$relacionamento)
            ->find($id);
    }

    public static function listar()
    {
        return self::with(self::$relacionamento);
    }
}
