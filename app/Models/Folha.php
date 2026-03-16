<?php

namespace App\Models;

use App\Casts\Periodo;
use App\MyLibs\RTG;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @property integer FOLHA_ID
 * @property string FOLHA_DESCRICAO
 * @property integer FOLHA_TIPO
 * @property integer VINCULO_ID
 * @property integer FOLHA_COMPETENCIA
 * @property integer FOLHA_QTD_SERVIDORES
 * @property float FOLHA_VALOR_TOTAL
 * @property string FOLHA_ARQUIVO
 * @property string FOLHA_CHECKSUM
 * @property string FOLHA_EMAIL_NOTIFICACAO
 */
class Folha extends Model
{
    use HasFactory;

    protected $table = "FOLHA";
    protected $primaryKey = "FOLHA_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        'FOLHA_DESCRICAO',
        'FOLHA_TIPO',
        'VINCULO_ID',
        'FOLHA_COMPETENCIA',
        'FOLHA_QTD_SERVIDORES',
        'FOLHA_VALOR_TOTAL',
        'FOLHA_ARQUIVO',
        'FOLHA_CHECKSUM',
    ];

    protected $casts = [
        'FOLHA_TIPO' => 'integer',
        'VINCULO_ID' => 'integer',
        'FOLHA_COMPETENCIA' => Periodo::class,
        'FOLHA_QTD_SERVIDORES' => 'integer',
        'FOLHA_VALOR_TOTAL' => 'integer',
    ];

    public function historicosFolhas()
    {
        return $this->hasMany(HistoricoFolha::class, 'FOLHA_ID', 'FOLHA_ID')
            ->orderBy('HISTORICO_FOLHA_DATA', 'DESC');
    }

    public function historicoUltimo()
    {
        return $this->hasOne(HistoricoFolha::class, 'FOLHA_ID', 'FOLHA_ID')
            ->where('HISTORICO_FOLHA_ULTIMO', 1);
    }

    public function tipoFolha()
    {
        return $this->hasOne(TabelaGenerica::class, 'COLUNA_ID', 'FOLHA_TIPO')
            ->where('TABELA_ID', RTG::TIPOS_FOLHA);
    }

    public function detalheFolhas()
    {
        return $this->hasMany(DetalheFolha::class, 'FOLHA_ID', 'FOLHA_ID');
    }

    public function setores()
    {
        return $this->hasManyThrough(Setor::class, FolhaSetor::class, 'FOLHA_ID', 'SETOR_ID', 'FOLHA_ID', 'SETOR_ID');
    }

    public function vinculo()
    {
        return $this->hasOne(Vinculo::class, 'VINCULO_ID', 'VINCULO_ID');
    }

    public static function relacionamento($request = null)
    {
        if ($request == null) {
            $request = new Request();
        }
        return [
            "historicoUltimo.statusFolha",
            "historicosFolhas.statusFolha",
            "historicosFolhas.usuario",
            "tipoFolha",
            "detalheFolhas" => function ($query) use ($request) {
                $query->when($request->FUNCIONARIO_ID, function (Builder $query) use ($request) {
                    $query->where('FUNCIONARIO_ID', $request->FUNCIONARIO_ID);
                });
            },
            "setores.unidade",
            "vinculo"
        ];
    }


    public static function listar($request)
    {
        return self::with(self::relacionamento($request))
            ->when($request->FUNCIONARIO_ID, function (Builder $query) use ($request) {
                $query->whereHas('detalheFolhas', function (Builder $query) use ($request) {
                    $query->where('FUNCIONARIO_ID', $request->FUNCIONARIO_ID);
                });
            })
            ->when($request->FOLHA_DESCRICAO, function (Builder $query) use ($request) {
                $query->where('FOLHA_DESCRICAO', "like", "%$request->FOLHA_DESCRICAO%");
            })
            ->when($request->VINCULO_ID, function (Builder $query) use ($request) {
                $query->where('VINCULO_ID', $request->VINCULO_ID);
            })
            ->when($request->FOLHA_TIPO, function (Builder $query) use ($request) {
                $query->where('FOLHA_TIPO', $request->FOLHA_TIPO);
            })
            ->when($request->FOLHA_COMPETENCIA, function (Builder $query) use ($request) {
                $periodo = explode('/', $request->FOLHA_COMPETENCIA);
                $copetencia = "$periodo[1]$periodo[0]";
                $query->where('FOLHA_COMPETENCIA', $copetencia);
            });
    }

    public static function buscar($id)
    {
        return self::with([
            "historicoUltimo.statusFolha",
            "detalheFolhas.folha.vinculo",
            "detalheFolhas.EventosDetalhesFolhas.evento.incidencia",
            "detalheFolhas.EventosDetalhesFolhas.evento.historicoEvento.formaCalculo",
            "detalheFolhas.funcionario.pessoa.cpf",
        ])
            ->find($id);
    }

    public function salvaFolha($lista_id_setores)
    {
        $retorno = '';
        DB::select("exec [dbo].[sp_gera_folha]?,N'?',?,?,?,N'?',?", array(
            $this->FOLHA_ID ? $this->FOLHA_ID : 'null',
            $this->FOLHA_DESCRICAO,
            $this->FOLHA_TIPO,
            $this->VINCULO_ID,
            $this->FOLHA_COMPETENCIA,
            $lista_id_setores,
            Auth::id(),
            $retorno
        ));
    }

    public static function processarFolha($request, $userId)
    {
        $periodo = explode('/', $request["FOLHA_COMPETENCIA"]);
        $competencia = "$periodo[1]$periodo[0]";
        DB::statement(
            "
                SET NOCOUNT ON ;
                exec [dbo].[sp_gera_folha]
                @p_descricao = ?,
                @p_tipo_id = ?,
                @p_vinculo_id = ?,
                @p_competencia = ?,
                @p_lista_id_setores = ?,
                @p_usuario_id = ?,
                @p_retorno = 0
            ",
            [
                $request["FOLHA_DESCRICAO"],
                $request["FOLHA_TIPO"],
                $request["VINCULO_ID"],
                $competencia,
                $request["setores"],
                $userId
            ]
        );
    }

    public static function reprocessarFolha($folhaId, $userId)
    {
        DB::statement(
            "
        SET NOCOUNT ON ;
        exec [dbo].[sp_gera_folha]
        @p_folha_id = ?,
		@p_usuario_id = ?,
		@p_retorno = 0
        ",
            [
                $folhaId,
                $userId
            ]
        );
    }
}
