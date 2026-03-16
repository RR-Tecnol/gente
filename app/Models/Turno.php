<?php

namespace App\Models;

use App\MyLibs\RTG;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $TURNO_ID
 * @property string $TURNO_DESCRICAO
 * @property string $TURNO_SIGLA
 * @property string $TURNO_HORA_INICIO
 * @property string $TURNO_HORA_FIM
 * @property int $TURNO_ATIVO
 *
 */
class Turno extends Model
{
    use SoftDeletes;

    const DELETED_AT = 'TURNO_DATA_EXCLUSAO';

    protected $table = "TURNO";
    protected $primaryKey = "TURNO_ID";
    public $timestamps = false;
    protected $fillable = [
        "TURNO_INTERVALO",
        "TURNO_DESCRICAO",
        "TURNO_SIGLA",
        "TURNO_HORA_INICIO",
        "TURNO_HORA_FIM",
        "TURNO_ATIVO",
    ];
    protected $casts = [
        "TURNO_INTERVALO" => "integer",
        "TURNO_ATIVO" => "integer"
    ];


    public static $relacionamento = [
        "intervalo",
    ];


    public function intervalo()
    {
        return $this->hasOne(TabelaGenerica::class, "COLUNA_ID", "TURNO_INTERVALO")
            ->where('TABELA_ID', RTG::INTERVALO)
            ->where("COLUNA_ID", "!=", 0);
    }

    public static function listar()
    {
        return self::where('TURNO_ATIVO', '=', 1)
            ->orderBy('TURNO_DESCRICAO')
            ->paginate();
    }

    public static function pesquisar($requisicao)
    {
        return self::with(self::$relacionamento)
            ->when($requisicao->TURNO_DESCRICAO, function (Builder $query) use ($requisicao) {
                return $query->where("TURNO_DESCRICAO", "like", "%" . $requisicao->TURNO_DESCRICAO . "%");
            })
            ->when($requisicao->TURNO_SIGLA, function (Builder $query) use ($requisicao) {
                return $query->where("TURNO_SIGLA", "like", "%" . $requisicao->TURNO_SIGLA . "%");
            })
            ->when($requisicao->TURNO_HORA_INICIO, function (Builder $query) use ($requisicao) {
                return $query->where("TURNO_HORA_INICIO", "like", "%" . $requisicao->TURNO_HORA_INICIO . "%");
            })
            ->when($requisicao->TURNO_HORA_FIM, function (Builder $query) use ($requisicao) {
                return $query->where("TURNO_HORA_FIM", "like", "%" . $requisicao->TURNO_HORA_FIM . "%");
            })
            ->where('TURNO_ATIVO', '=', 1)
            ->orderBy('TURNO_DESCRICAO')
            ->get();
    }

    public static function search($requisicao)
    {
        return self::with(self::$relacionamento)
            ->when($requisicao->TURNO_DESCRICAO, function (Builder $query) use ($requisicao) {
                return $query->where("TURNO_DESCRICAO", "like", "%" . $requisicao->TURNO_DESCRICAO . "%");
            })
            ->when($requisicao->TURNO_SIGLA, function (Builder $query) use ($requisicao) {
                return $query->where("TURNO_SIGLA", "like", "%" . $requisicao->TURNO_SIGLA . "%");
            })
            ->when($requisicao->TURNO_HORA_INICIO, function (Builder $query) use ($requisicao) {
                return $query->where("TURNO_HORA_INICIO", "like", "%" . $requisicao->TURNO_HORA_INICIO . "%");
            })
            ->when($requisicao->TURNO_HORA_FIM, function (Builder $query) use ($requisicao) {
                return $query->where("TURNO_HORA_FIM", "like", "%" . $requisicao->TURNO_HORA_FIM . "%");
            })
            ->when($requisicao->orderBy, function (Builder $query) use ($requisicao) {
                $requisicao->sort = $requisicao->sort ?: 'asc';
                $query->orderBy($requisicao->orderBy, $requisicao->sort);
            })
            ->when(!$requisicao->orderBy, function (Builder $query) {
                $query->orderBy('TURNO_DESCRICAO');
            })
            ->where('TURNO_ATIVO', '=', 1)
            ->paginate();
    }

    public static function buscar($id)
    {
        return self::with(self::$relacionamento)->find($id);
    }
}
