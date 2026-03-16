<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SetorAtribuicao extends Model
{
    protected $table = "SETOR_ATRIBUICAO";
    protected $primaryKey = "SETOR_ATRIBUICAO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "SETOR_ID",
        "ATRIBUICAO_ID",
        "SETOR_ATRIBUICAO_QTD",
    ];

    protected $casts = [
        "SETOR_ID" => "integer",
        "ATRIBUICAO_ID" => "integer",
        "SETOR_ATRIBUICAO_QTD" => "integer",
    ];

    protected static $relacionamento = [
        'setor',
        'atribuicao',
    ];

    public function setor()
    {
        return $this->hasOne(Setor::class, 'SETOR_ID', 'SETOR_ID')
            ->orderBy('SETOR_NOME');
    }

    public function atribuicao()
    {
        return $this->hasOne(Atribuicao::class, 'ATRIBUICAO_ID', 'ATRIBUICAO_ID')
            ->orderBy('ATRIBUICAO_NOME');
    }

    public static function listar()
    {
        return self::with(self::$relacionamento);
    }

    public static function buscar($id)
    {
        return self::with(self::$relacionamento)
            ->find($id);
    }

    public static function getBySetorNoPag($setorId)
    {
        return self::with(self::$relacionamento)
            ->where("SETOR_ID", $setorId)
            ->get();
    }
}
