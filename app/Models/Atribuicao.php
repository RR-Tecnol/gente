<?php

namespace App\Models;

use App\MyLibs\RTG;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property integer $ATRIBUICAO_ID
 * @property integer $ATRIBUICAO_TIPO
 * @property string $ATRIBUICAO_NOME
 * @property string $ATRIBUICAO_SIGLA
 * @property integer $ATRIBUICAO_GESTAO
 * @property integer $ATRIBUICAO_ESCOLARIDADE
 * @property integer $ATRIBUICAO_ATIVA
 * @method static Atribuicao find($id)
 */
class Atribuicao extends Model
{
    use HasFactory, SoftDeletes;

    const DELETED_AT = 'ATRIBUICAO_DATA_EXCLUSAO';

    protected $table = "ATRIBUICAO";
    protected $primaryKey = "ATRIBUICAO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "ATRIBUICAO_TIPO",
        "ATRIBUICAO_NOME",
        "ATRIBUICAO_SIGLA",
        "ATRIBUICAO_GESTAO",
        "ATRIBUICAO_ESCOLARIDADE",
        "ATRIBUICAO_CBO",
        "ATRIBUICAO_ATIVA",
    ];
    protected $casts = [
        "ATRIBUICAO_ID" => "integer",
        "ATRIBUICAO_TIPO" => "integer",
        "ATRIBUICAO_GESTAO" => "integer",
        "ATRIBUICAO_ESCOLARIDADE" => "integer",
        "ATRIBUICAO_CBO" => "string",
        "ATRIBUICAO_ATIVA" => "integer",
    ];
    protected static $relacionamentos = [
        "atribuicaoTipo",
        "atribuicaoEscolaridade",
        'atribuicaoConfigs.histAtribuicaoConfig.usuario',
        'atribuicaoConfigs.atribuicaoConfigPorteUnidade',
    ];

    public function atribuicaoTipo()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "ATRIBUICAO_TIPO")
            ->where("TABELA_ID", "=", RTG::TIPOS_DE_ATRIBUICOES)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function atribuicaoEscolaridade()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "ATRIBUICAO_ESCOLARIDADE")
            ->where("TABELA_ID", "=", RTG::ESCOLARIDADE)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function atribuicaoConfigs()
    {
        return $this->hasMany(AtribuicaoConfig::class, 'ATRIBUICAO_ID', 'ATRIBUICAO_ID');
    }

    public static function listAll($soAtivos = 1)
    {
        return self::with(self::$relacionamentos)
            ->when($soAtivos, function (Builder $q) {
                $q->where("ATRIBUICAO_ATIVA", 1);
            })
            ->get();
    }

    public static function buscar($id)
    {
        return self::with(self::$relacionamentos)
            ->find($id);
    }

    public static function listar($request)
    {
        return self::with(self::$relacionamentos)
            ->when($request->ATRIBUICAO_NOME, function (Builder $query) use ($request) {
                $query->where('ATRIBUICAO_NOME', 'like', "%$request->ATRIBUICAO_NOME%");
            })
            ->when($request->ATRIBUICAO_SIGLA, function (Builder $query) use ($request) {
                $query->where('ATRIBUICAO_SIGLA', 'like', "%$request->ATRIBUICAO_SIGLA%");
            })
            ->when($request->ATRIBUICAO_TIPO, function (Builder $query) use ($request) {
                $query->where('ATRIBUICAO_TIPO', $request->ATRIBUICAO_TIPO);
            })
            ->when($request->ATRIBUICAO_ESCOLARIDADE, function (Builder $query) use ($request) {
                $query->where('ATRIBUICAO_ESCOLARIDADE', $request->ATRIBUICAO_ESCOLARIDADE);
            })
            ->when($request->ATRIBUICAO_GESTAO, function (Builder $query) use ($request) {
                $query->where('ATRIBUICAO_GESTAO', $request->ATRIBUICAO_GESTAO);
            })
            ->when($request->orderBy, function (Builder $query) use ($request) {
                $request->sort = $request->sort ?: 'asc';
                $query->orderBy($request->orderBy, $request->sort);
            })
            ->when(!$request->orderBy, function (Builder $query) {
                $query->orderBy('ATRIBUICAO_NOME');
            });
    }

    public static function search($valorPesquisa, $somenteAtivos = 1)
    {
        return self::with(self::$relacionamentos)
            ->where("ATRIBUICAO_NOME", "like", "%$valorPesquisa%")
            ->orWhere("ATRIBUICAO_SIGLA", "like", "%$valorPesquisa%")
            ->when($somenteAtivos == 1, function ($q) {
                $q->where("ATRIBUICAO_ATIVA", 1);
            })
            ->paginate(10);
    }
}
