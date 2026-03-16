<?php

namespace App\Models;

use App\Casts\Periodo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VigenciaImposto extends Model
{
    use HasFactory;
    protected $table = "VIGENCIA_IMPOSTO";
    protected $primaryKey = "VIGENCIA_IMPOSTO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "VIGENCIA_IMPOSTO_INICIO",
        "VIGENCIA_IMPOSTO_FIM",
        "EVENTO_ID",
    ];

    protected $casts = [
        "VIGENCIA_IMPOSTO_INICIO" => Periodo::class,
        "VIGENCIA_IMPOSTO_FIM" => Periodo::class,
    ];

    public function tabelaImpostos()
    {
        return $this->hasMany(TabelaImposto::class, 'VIGENCIA_IMPOSTO_ID', 'VIGENCIA_IMPOSTO_ID');
    }

    public static $relacionamento = [
        'tabelaImpostos'
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

    public static function boot()
    {
        parent::boot();

        static::deleting(function ($dado) {
            $dado->tabelaImpostos()->delete();
        });
    }
}
