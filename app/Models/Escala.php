<?php

namespace App\Models;

use App\Casts\Periodo;
use App\MyLibs\PerfilEnum;
use App\MyLibs\RTG;
use App\MyLibs\StatusEscalaEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * @property integer ESCALA_ID
 * @property integer SETOR_ID
 * @property string ESCALA_COMPETENCIA
 * @property string ESCALA_DESCRICAO
 * @property string ESCALA_OBSERVACAO
 */
class Escala extends Model
{
    protected $table = "ESCALA";
    protected $primaryKey = "ESCALA_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "SETOR_ID",
        "ESCALA_COMPETENCIA",
        "ESCALA_DESCRICAO",
        "ESCALA_OBSERVACAO",
        "TIPO_ESCALA_ID",
    ];

    protected $casts = [
        "ESCALA_ID" => 'integer',
        "SETOR_ID" => 'integer',
        "ESCALA_COMPETENCIA" => Periodo::class,
        "TIPO_ESCALA_ID" => 'integer'
    ];

    public function setor()
    {
        return $this->hasOne(Setor::class, 'SETOR_ID', 'SETOR_ID');
    }

    public function detalheEscalas()
    {
        return $this->hasMany(DetalheEscala::class, "ESCALA_ID", "ESCALA_ID");
    }

    public function historicos()
    {
        return $this->hasMany(HistoricoEscala::class, 'ESCALA_ID', 'ESCALA_ID')
            ->orderBy('HISTORICO_ESCALA_ID', 'desc');
    }

    public function historicoUltimo()
    {
        return $this->hasOne(HistoricoEscala::class, 'ESCALA_ID', 'ESCALA_ID')
            ->where('HISTORICO_ESCALA_ULTIMO', 1);
    }

    public function tipoEscala()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "TIPO_ESCALA_ID")
            ->where("TABELA_ID", "=", RTG::TIPO_ESCALA)
            ->where("COLUNA_ID", "!=", 0);
    }

    public static function relacionamento()
    {
        return [
            "setor.unidade",
            "tipoEscala",
            "detalheEscalas.detalheEscalaItens.turno",
            "detalheEscalas.funcionario.pessoa.escolaridade",
            "detalheEscalas.funcionario.pessoa.sexo",
            "detalheEscalas.funcionario.lotacoes.vinculo",
            "detalheEscalas.funcionario.lotacoes.atribuicaoLotacoes.atribuicaoLotacaoCargaHoraria",
            "detalheEscalas.funcionario.ferias",
            "detalheEscalas.funcionario.afastamentos.tipoAfastamento",
            "detalheEscalas.detalheEscalaAutoriza.usuario",
            "detalheEscalas.detalheEscalaAlertas" => function ($query) {
                $query->whereHas('tipoAlerta', function ($query) {
                    $query->where('TIPO_ALERTA_VISIVEL', 1);
                });
            },
            "detalheEscalas.detalheEscalaAlertas.tipoAlerta",
            "detalheEscalas.detalheEscalaAlertas" => function ($query) {
                $query->where('DETALHE_ESCALA_ALERTA_ULTIMO', 1);
            },
            "detalheEscalas.atribuicao",
            "historicos.usuario",
            "historicos.statusEscala",
            "historicoUltimo.statusEscala",
            "historicoUltimo.usuario",
        ];
    }

    public static $relacionamentoExcluir = [
        "detalheEscalas.detalheEscalaItens",
        "detalheEscalas.detalheEscalaAutoriza",
        "detalheEscalas.detalheEscalaAlertas",
        "historicos",
    ];

    public static $relacionamentoView = [
        "setor.unidade",
        "tipoEscala",
        "historicos.usuario",
        "historicos.statusEscala",
        "historicoUltimo.statusEscala",
        "historicoUltimo.usuario",
    ];

    public static function listar($requisicao)
    {
        $user = Auth::user();
        $ehPerfilDev = $user->usuarioPerfis()->where('PERFIL_ID', PerfilEnum::DESENVOLVEDOR)->exists();
        return self::with(self::$relacionamentoView)
            ->withCount('detalheEscalas')
            ->when($requisicao->UNIDADE_NOME, function (Builder $query) use ($requisicao) {
                return $query->whereHas("setor.unidade", function ($query) use ($requisicao) {
                    return $query->where("UNIDADE_NOME", "like", "%$requisicao->UNIDADE_NOME%");
                });
            })
            ->when($requisicao->SETOR_NOME, function (Builder $query) use ($requisicao) {
                return $query->whereHas("setor", function ($query) use ($requisicao) {
                    return $query->where("SETOR_NOME", "like", "%$requisicao->SETOR_NOME%");
                });
            })
            ->when($requisicao->ESCALA_DESCRICAO, function (Builder $query) use ($requisicao) {
                return $query->where("ESCALA_DESCRICAO", "like", "%$requisicao->ESCALA_DESCRICAO%");
            })
            ->when($requisicao->ESCALA_COMPETENCIA, function (Builder $query) use ($requisicao) {
                $periodo = explode('/', $requisicao->ESCALA_COMPETENCIA);
                $valor = "$periodo[1]$periodo[0]";
                return $query->where("ESCALA_COMPETENCIA", "like", "%$valor%");
            })
            ->when($requisicao->orderBy, function (Builder $query) use ($requisicao) {
                $requisicao->sort = $requisicao->sort ?: 'asc';
                $query->orderBy($requisicao->orderBy, $requisicao->sort);
            })
            ->when(!$requisicao->orderBy, function (Builder $query) {
                $query->orderBy('ESCALA_ID', 'desc');
            })
            ->when(!$ehPerfilDev, function (Builder $query) use ($user){
                $query->whereHas("setor", function ($q) use ($user) {
                    $ehPerfilCordenadorSetor = $user->usuarioPerfis()->where('PERFIL_ID', PerfilEnum::COORD_DE_SETOR)->exists();
                    if($ehPerfilCordenadorSetor){
                        $q->whereIn('SETOR_ID', $user->usuarioSetores->pluck('SETOR_ID'));
                    }else{
                        $q->whereIn('UNIDADE_ID', $user->usuarioUnidades->pluck('UNIDADE_ID'));
                    }
                });
            });
    }

    public static function listarAvaliacao($request)
    {
        return self::listar($request)
            ->with([
                "detalheEscalas.funcionario.pessoa",
                "detalheEscalas.atribuicao",
                "detalheEscalas.detalheEscalaAlertas.tipoAlerta",
                "detalheEscalas.detalheEscalaAutoriza",
            ])
            ->whereHas("historicoUltimo", function ($q) {
                $q->whereIn("HISTORICO_ESCALA_STATUS", [StatusEscalaEnum::CADASTRADA, StatusEscalaEnum::ATUALIZADA, StatusEscalaEnum::AVALIADA, StatusEscalaEnum::CLONADA]);
            })
            ->whereHas("detalheEscalas")
            ->paginate();
    }

    public static function listarDeferidas($request)
    {
        return self::listar($request)
            ->whereHas("historicoUltimo", function ($q) {
                $q->whereIn("HISTORICO_ESCALA_STATUS", [StatusEscalaEnum::DEFERIDA]);
            });
    }

    public static function buscar($request)
    {
        return self::with(self::relacionamento())
            ->find($request);
    }

    public static function buscarView($request)
    {
        return self::with(self::$relacionamentoView)
            ->find($request);
    }

    public static function buscarExcluir($id)
    {
        return self::with(self::$relacionamentoExcluir)
            ->find($id);
    }

    public static function excluir(Escala $escala)
    {
        // Carrega os relacionamentos necessários
        $escala->load(self::$relacionamentoExcluir);

        // 1. Exclui detalheEscalas e seus relacionamentos
        foreach ($escala->detalheEscalas ?? [] as $detalheEscala) {
            foreach ($detalheEscala->detalheEscalaItens ?? [] as $item) {
                $item->delete();
            }

            foreach ($detalheEscala->detalheEscalaAlertas ?? [] as $alerta) {
                $alerta->delete();
            }

            if ($detalheEscala->detalheEscalaAutoriza) {
                $detalheEscala->detalheEscalaAutoriza->delete();
            }

            $detalheEscala->delete();
        }

        // 2. Exclui históricos (coleção)
        foreach ($escala->historicos ?? [] as $historico) {
            $historico->delete();
        }

        // 4. Por fim, exclui a própria escala
        $escala->delete();
    }
}
