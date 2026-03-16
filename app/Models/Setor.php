<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int SETOR_ID
 * @property int UNIDADE_ID
 * @property string SETOR_NOME
 * @property string SETOR_SIGLA
 * @property int SETOR_ATIVO
 *
 */
class Setor extends Model
{
    protected $table = "SETOR";
    protected $primaryKey = "SETOR_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "UNIDADE_ID",
        "SETOR_NOME",
        "SETOR_SIGLA",
        "SETOR_ATIVO",
    ];
    protected $casts = [
        "SETOR_ATIVO" => "integer",
        "UNIDADE_ID" => "integer",
        "SETOR_ID" => "integer",
    ];
    protected static $relacionamentos = [
        'unidade'
    ];

    public function unidade()
    {
        return $this->hasOne(Unidade::class, 'UNIDADE_ID', 'UNIDADE_ID');
    }

    public function lotacao()
    {
        return $this->hasMany(Lotacao::class, 'SETOR_ID', 'SETOR_ID');
    }

    public function setoresAtribuicoes()
    {
        return $this->hasMany(SetorAtribuicao::class, 'SETOR_ID', 'SETOR_ID');
    }

    public static function relacionamento()
    {
        return [
            "unidade",
            "lotacao",
            "setoresAtribuicoes.atribuicao",
        ];
    }

    public static function listar($request)
    {
        if ($request->unidadeId)
            return self::with(self::relacionamento())
                ->where('UNIDADE_ID', '=', $request->unidadeId)
                ->where("SETOR_ATIVO", 1)
                ->orderBy('SETOR_NOME')
                ->get();
        else
            return self::with(self::relacionamento())
                ->orderBy('SETOR_NOME')
                ->where("SETOR_ATIVO", 1)
                ->get();
    }

    public static function listAll($soAtivos = 1)
    {
        return self::with(self::$relacionamentos)
            ->when($soAtivos, function ($q) {
                $q->where("SETOR_ATIVO", 1);
            })->get();
    }

    public static function pesquisar($requisicao)
    {
        return self::with(self::relacionamento())
            ->when($requisicao->UNIDADE_ID, function (Builder $query) use ($requisicao) {
                return $query->where("UNIDADE_ID", "=", $requisicao->UNIDADE_ID);
            })
            ->when($requisicao->SETOR_NOME, function (Builder $query) use ($requisicao) {
                return $query->where("SETOR_NOME", "like", "%$requisicao->SETOR_NOME%");
            })
            ->when($requisicao->SETOR_SIGLA, function (Builder $query) use ($requisicao) {
                return $query->where("SETOR_SIGLA", "like", "%$requisicao->SETOR_SIGLA%");
            })
            ->when($requisicao->SETOR_ATIVO, function (Builder $query) use ($requisicao) {
                return $query->where("SETOR_ATIVO", "=", $requisicao->SETOR_ATIVO);
            })
            ->whereHas('unidade', function (Builder $query) {
                return $query->where("UNIDADE_ATIVA", "=", 1);
            })
            ->orderBy('SETOR_NOME')
            ->get();
    }

    public static function buscar($id)
    {
        return self::with(self::relacionamento())
            ->find($id);
    }

    public static function getByUnidade($unidadeId)
    {
        return self::with([])->where('UNIDADE_ID', $unidadeId)->get();
    }
}
