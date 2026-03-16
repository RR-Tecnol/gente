<?php

namespace App\Models;

use App\Casts\Periodo;
use Illuminate\Database\Eloquent\Model;

class LotacaoEvento extends Model
{
    protected $table = "LOTACAO_EVENTO";
    protected $primaryKey = "LOTACAO_EVENTO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "LOTACAO_ID",
        "EVENTO_ID",
        "LOTACAO_EVENTO_INFO",
        "LOTACAO_EVENTO_INICIO",
        "LOTACAO_EVENTO_FIM",
        "LOTACAO_EVENTO_VALOR",
        "LOTACAO_EVENTO_EXCLUIDO",
        "LOTACAO_EVENTO_DATA_CADASTRO",
        "USUARIO_ID",
    ];

    protected $casts = [
        "LOTACAO_ID" => 'integer',
        "EVENTO_ID" => 'integer',
        "LOTACAO_EVENTO_INICIO" => Periodo::class,
        "LOTACAO_EVENTO_FIM" => Periodo::class,
        "LOTACAO_EVENTO_EXCLUIDO" => 'integer',
        "USUARIO_ID" => 'integer'
    ];

    public static $relacionamento = [
        'lotacao.setor.unidade',
        'lotacao.funcionario.pessoa',
        'evento.incidencia',
        'evento.historicoEvento.formaCalculo',
        'usuario',
    ];

    public function lotacao()
    {
        return $this->hasOne(Lotacao::class, 'LOTACAO_ID', 'LOTACAO_ID');
    }

    public function evento()
    {
        return $this->hasOne(Evento::class, 'EVENTO_ID', 'EVENTO_ID');
    }

    public function usuario()
    {
        return $this->hasOne(Usuario::class, 'USUARIO_ID', 'USUARIO_ID');
    }

    public static function listar($request)
    {
        return self::with(self::$relacionamento)
            ->where('LOTACAO_EVENTO_EXCLUIDO', 0);
    }

    public static function buscar($id)
    {
        return self::with(self::$relacionamento)
            ->find($id);
    }
}
