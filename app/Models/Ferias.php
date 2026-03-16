<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Ferias extends Model
{
    protected $table = "FERIAS";
    protected $primaryKey = "FERIAS_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "FUNCIONARIO_ID",
        "FERIAS_DATA_INICIO",
        "FERIAS_DATA_FIM",
        "FERIAS_AQUISITIVO_INICIO",
        "FERIAS_AQUISITIVO_FIM",
    ];

    public function funcionario()
    {
        return $this->hasOne(Funcionario::class, 'FUNCIONARIO_ID', 'FUNCIONARIO_ID');
    }

    public function anexoFerias()
    {
        return $this->hasMany(AnexoFerias::class, 'FERIAS_ID', 'FERIAS_ID');
    }

    protected $casts = [
        "FERIAS_AQUISITIVO_INICIO" => "integer",
        "FERIAS_AQUISITIVO_FIM" => "integer"
    ];

    public static $relacionamento = [
        'funcionario.pessoa',
        'anexoFerias'
    ];

    public static function listar($request)
    {
        return self::with(self::$relacionamento)
            ->when($request->PESSOA_NOME, function (Builder $query) use ($request) {
                $query->whereHas('funcionario.pessoa', function (Builder $query) use ($request) {
                    $query->where('PESSOA_NOME', 'like', "%$request->PESSOA_NOME%");
                });
            })
            ->when($request->FERIAS_DATA_INICIO, function (Builder $query) use ($request) {
                $query->where('FERIAS_DATA_INICIO', $request->FERIAS_DATA_INICIO);
            })
            ->when($request->FERIAS_DATA_FIM, function (Builder $query) use ($request) {
                $query->where('FERIAS_DATA_FIM', $request->FERIAS_DATA_FIM);
            })
            ->when($request->FERIAS_AQUISITIVO_INICIO, function (Builder $query) use ($request) {
                $query->where('FERIAS_AQUISITIVO_INICIO', $request->FERIAS_AQUISITIVO_INICIO);
            })
            ->when($request->FERIAS_AQUISITIVO_FIM, function (Builder $query) use ($request) {
                $query->where('FERIAS_AQUISITIVO_FIM', $request->FERIAS_AQUISITIVO_FIM);
            })
            ->when($request->orderBy, function (Builder $query) use ($request) {
                $request->sort = $request->sort ?: 'asc';
                $query->orderBy($request->orderBy, $request->sort);
            })
            ->when(!$request->orderBy, function (Builder $query) {
                $query->orderBy('FERIAS_ID');
            });
    }

    public static function buscar($id)
    {
        return self::with(self::$relacionamento)
            ->find($id);
    }

    /**
     * Retorna funcionários com período aquisitivo de férias vencido ou próximo de vencer,
     * sem férias de gozo marcadas (FERIAS_DATA_INICIO nulo).
     *
     * Prazo legal: 31/dez do ano seguinte ao FERIAS_AQUISITIVO_FIM
     *   Ex: período 2023/2024 → prazo até 31/12/2025
     *
     * Urgências:
     *   - VENCIDO  : prazo < hoje
     *   - CRITICO  : 1 a 60 dias restantes
     *   - ATENCAO  : 61 a 120 dias restantes
     *
     * @param int|null $setorId  Restringir ao setor (para COORD_DE_SETOR)
     * @return array{vencidos: array, criticos: array, atencao: array, total: int}
     */
    public static function alertaVencer(?int $setorId = null): array
    {
        $hoje120 = now()->addDays(120)->toDateString();

        $base = DB::table('FERIAS as f')
            ->join('FUNCIONARIO as fun', 'fun.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'fun.PESSOA_ID')
            ->join('LOTACAO as l', function ($join) {
                $join->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                    ->where(function ($q) {
                        $q->whereNull('l.LOTACAO_DATA_FIM')
                            ->orWhere('l.LOTACAO_DATA_FIM', '>=', DB::raw('GETDATE()'));
                    });
            })
            ->join('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->join('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
            ->select(
                'f.FERIAS_ID',
                'f.FUNCIONARIO_ID',
                'f.FERIAS_AQUISITIVO_INICIO',
                'f.FERIAS_AQUISITIVO_FIM',
                'f.FERIAS_DATA_INICIO',
                'f.FERIAS_DATA_FIM',
                'p.PESSOA_NOME',
                's.SETOR_NOME',
                's.SETOR_ID',
                'u.UNIDADE_NOME',
                'l.LOTACAO_ID',
                DB::raw("DATEADD(year, 1, DATEFROMPARTS(f.FERIAS_AQUISITIVO_FIM, 12, 31)) AS PRAZO_GOZO"),
                DB::raw("DATEDIFF(day, GETDATE(), DATEADD(year, 1, DATEFROMPARTS(f.FERIAS_AQUISITIVO_FIM, 12, 31))) AS DIAS_RESTANTES")
            )
            // Apenas registros SEM data de gozo marcada
            ->whereNull('f.FERIAS_DATA_INICIO')
            // Prazo em até 120 dias OU já vencido
            ->whereRaw("DATEADD(year, 1, DATEFROMPARTS(f.FERIAS_AQUISITIVO_FIM, 12, 31)) <= ?", [$hoje120])
            ->when($setorId, fn($q) => $q->where('l.SETOR_ID', $setorId))
            // Remove duplicatas quando funcionário tem múltiplas lotações ativas
            ->distinct()
            ->orderBy('DIAS_RESTANTES')
            ->get();

        $resultado = ['vencidos' => [], 'criticos' => [], 'atencao' => []];

        foreach ($base as $item) {
            $arr = (array) $item;
            if ($item->DIAS_RESTANTES < 0) {
                $arr['urgencia'] = 'VENCIDO';
                $resultado['vencidos'][] = $arr;
            } elseif ($item->DIAS_RESTANTES <= 60) {
                $arr['urgencia'] = 'CRITICO';
                $resultado['criticos'][] = $arr;
            } else {
                $arr['urgencia'] = 'ATENCAO';
                $resultado['atencao'][] = $arr;
            }
        }

        $resultado['total'] = count($resultado['vencidos'])
            + count($resultado['criticos'])
            + count($resultado['atencao']);

        return $resultado;
    }
}
