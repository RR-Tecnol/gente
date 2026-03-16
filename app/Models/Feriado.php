<?php

namespace App\Models;

use App\MyLibs\RTG;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Feriado extends Model
{
    protected $table = "FERIADO";
    protected $primaryKey = "FERIADO_ID";
    public $timestamps = false;
    protected $fillable = [
        "FERIADO_DATA",
        "FERIADO_DESCRICAO",
        "FERIADO_TIPO",
        "FERIADO_ATIVO",
    ];

    protected static $relacionamentos = [
        'tipo'
    ];

    protected $casts = [
        "FERIADO_ID" => "integer",
        "FERIADO_TIPO" => "integer",
        "FERIADO_ATIVO" => "integer"
    ];

    public function tipo()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "FERIADO_TIPO")
            ->where("TABELA_ID", "=", RTG::TIPO_FERIADO)
            ->where("COLUNA_ID", "!=", 0);
    }

    public static function listar($request)
    {
        return self::with(self::$relacionamentos)
            ->when($request->FERIADO_DATA, function (Builder $query) use ($request) {
                $query->where('FERIADO_DATA', $request->FERIADO_DATA);
            })
            ->when($request->FERIADO_DESCRICAO, function (Builder $query) use ($request) {
                $query->where('FERIADO_DESCRICAO', 'like', "%$request->FERIADO_DESCRICAO%");
            })
            ->when($request->FERIADO_TIPO, function (Builder $query) use ($request) {
                $query->where('FERIADO_TIPO', $request->FERIADO_TIPO);
            })
            ->when($request->orderBy, function (Builder $query) use ($request) {
                $request->sort = $request->sort ?: 'asc';
                $query->orderBy($request->orderBy, $request->sort);
            })
            ->when(!$request->orderBy, function (Builder $query) {
                $query->orderBy('FERIADO_DESCRICAO');
            });
    }

    public static function buscar($request)
    {
        return self::with(self::$relacionamentos)
            ->find($request);
    }

    public static function buscarPorAno($ano)
    {
        return self::with(self::$relacionamentos)
            ->where(DB::raw('YEAR(FERIADO_DATA)'), '=', $ano)
            ->orderBy('FERIADO_DATA')
            ->get();
    }

    public static function buscarEntreDatas($dataInicial, $dataFinal)
    {
        return self::with(self::$relacionamentos)
            ->whereBetween('FERIADO_DATA', [$dataInicial, $dataFinal])
            ->orderBy('FERIADO_DATA')
            ->get();
    }

    public static function buscarProximoFeriado($data)
    {
        return self::with(self::$relacionamentos)
            ->whereDate('FERIADO_DATA', '>', [$data])
            ->where(DB::raw('YEAR(FERIADO_DATA)'), '=', Carbon::parse($data)->format('Y'))
            ->first();
    }

    public static function buscarFeriadoAnterior($data)
    {
        return self::with(self::$relacionamentos)
            ->whereDate('FERIADO_DATA', '<', [$data])
            ->where(DB::raw('YEAR(FERIADO_DATA)'), '=', Carbon::parse($data)->format('Y'))
            ->first();
    }

    public static function buscarDataFeriado($data): ?Feriado
    {
        return self::with(self::$relacionamentos)
            ->whereDate('FERIADO_DATA', '=', [$data])
            ->first();
    }

    public static function buscarFeriadoMesAno($mesAno)
    {
        return self::with(self::$relacionamentos)
            ->where(DB::raw('YEAR(FERIADO_DATA)'), '=', Carbon::parse($mesAno)->format('Y'))
            ->where(DB::raw('MONTH(FERIADO_DATA)'), '=', Carbon::parse($mesAno)->format('m'))
            ->get();
    }
}
