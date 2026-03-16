<?php

namespace App\Models;

use App\MyLibs\RTG;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Afastamento extends Model
{
    protected $table = "AFASTAMENTO";
    protected $primaryKey = "AFASTAMENTO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "FUNCIONARIO_ID",
        "AFASTAMENTO_DATA_INICIO",
        "AFASTAMENTO_DATA_FIM",
        "AFASTAMENTO_TIPO",
    ];

    public function funcionario()
    {
        return $this->hasOne(Funcionario::class, 'FUNCIONARIO_ID', 'FUNCIONARIO_ID');
    }

    public function tipoAfastamento()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "AFASTAMENTO_TIPO")
            ->where("TABELA_ID", "=", RTG::TIPO_AFASTAMENTO)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function anexoAfastamento()
    {
        return $this->hasMany(AnexoAfastamento::class, 'AFASTAMENTO_ID', 'AFASTAMENTO_ID');
    }

    protected $casts = [
        "AFASTAMENTO_TIPO" => "integer",
    ];

    public static $relacionamento = [
        'funcionario.pessoa',
        'tipoAfastamento',
        'anexoAfastamento'
    ];

    public static function listar($request)
    {
        return self::with(self::$relacionamento)
            ->when($request->PESSOA_NOME, function (Builder $query) use ($request) {
                $query->whereHas('funcionario.funcionario.pessoa', function (Builder $query) use ($request) {
                    $query->where('PESSOA_NOME', 'like', "%$request->PESSOA_NOME%");
                });
            })
            ->when($request->AFASTAMENTO_DATA_INICIO, function (Builder $query) use ($request) {
                $query->where('AFASTAMENTO_DATA_INICIO', $request->AFASTAMENTO_DATA_INICIO);
            })
            ->when($request->AFASTAMENTO_DATA_FIM, function (Builder $query) use ($request) {
                $query->where('AFASTAMENTO_DATA_FIM', $request->AFASTAMENTO_DATA_FIM);
            })
            ->when($request->AFASTAMENTO_TIPO, function (Builder $query) use ($request) {
                $query->where('AFASTAMENTO_TIPO', $request->AFASTAMENTO_TIPO);
            })
            ->when($request->orderBy, function (Builder $query) use ($request) {
                $request->sort = $request->sort ?: 'asc';
                $query->orderBy($request->orderBy, $request->sort);
            })
            ->when(!$request->orderBy, function (Builder $query) {
                $query->orderBy('AFASTAMENTO_ID');
            });
    }

    public static function buscar($id)
    {
        return self::with(self::$relacionamento)
            ->find($id);
    }

    /**
     * Retorna afastamentos ativos com data de fim próxima de expirar.
     *
     * Urgências:
     *   - VENCIDO   : AFASTAMENTO_DATA_FIM < hoje (afastamento encerrado, sem registro de retorno)
     *   - CRITICO   : expira em 1 a 15 dias
     *   - ATENCAO   : expira em 16 a 60 dias
     *
     * @param int|null $setorId  Filtrar por setor do gestor
     * @return array{vencidos: array, criticos: array, atencao: array, total: int}
     */
    public static function alertaExpirar(?int $setorId = null): array
    {
        $hoje60 = now()->addDays(60)->toDateString();

        $base = DB::table('AFASTAMENTO as a')
            ->join('FUNCIONARIO as fun', 'fun.FUNCIONARIO_ID', '=', 'a.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'fun.PESSOA_ID')
            ->join('LOTACAO as l', function ($join) {
                $join->on('l.FUNCIONARIO_ID', '=', 'a.FUNCIONARIO_ID')
                    ->where(function ($q) {
                        $q->whereNull('l.LOTACAO_DATA_FIM')
                            ->orWhere('l.LOTACAO_DATA_FIM', '>=', DB::raw('GETDATE()'));
                    });
            })
            ->join('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->join('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
            ->leftJoin('TABELA_GENERICA as tg', function ($join) {
                $join->on('tg.COLUNA_ID', '=', 'a.AFASTAMENTO_TIPO')
                    ->where('tg.TABELA_ID', '=', RTG::TIPO_AFASTAMENTO)
                    ->where('tg.COLUNA_ID', '!=', 0);
            })
            ->select(
                'a.AFASTAMENTO_ID',
                'a.FUNCIONARIO_ID',
                'a.AFASTAMENTO_DATA_INICIO',
                'a.AFASTAMENTO_DATA_FIM',
                'a.AFASTAMENTO_TIPO',
                'tg.COLUNA_DESCRICAO as TIPO_DESCRICAO',
                'p.PESSOA_NOME',
                's.SETOR_NOME',
                's.SETOR_ID',
                'u.UNIDADE_NOME',
                'l.LOTACAO_ID',
                DB::raw("DATEDIFF(day, GETDATE(), a.AFASTAMENTO_DATA_FIM) AS DIAS_RESTANTES")
            )
            // Apenas afastamentos com data de fim definida e dentro da janela
            ->whereNotNull('a.AFASTAMENTO_DATA_FIM')
            ->where('a.AFASTAMENTO_DATA_FIM', '<=', $hoje60)
            // Afastamento ainda ativo (começou antes de hoje)
            ->where('a.AFASTAMENTO_DATA_INICIO', '<=', DB::raw('GETDATE()'))
            ->when($setorId, fn($q) => $q->where('l.SETOR_ID', $setorId))
            ->distinct()
            ->orderBy('DIAS_RESTANTES')
            ->get();

        $resultado = ['vencidos' => [], 'criticos' => [], 'atencao' => []];

        foreach ($base as $item) {
            $arr = (array) $item;
            if ($item->DIAS_RESTANTES < 0) {
                $arr['urgencia'] = 'VENCIDO';
                $resultado['vencidos'][] = $arr;
            } elseif ($item->DIAS_RESTANTES <= 15) {
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
