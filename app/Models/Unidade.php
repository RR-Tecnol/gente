<?php

namespace App\Models;

use App\MyLibs\PerfilEnum;
use App\MyLibs\RTG;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @property int UNIDADE_ID
 * @property string UNIDADE_NOME
 * @property string UNIDADE_CNES
 * @property string UNIDADE_ENDERECO
 * @property string UNIDADE_BAIRRO
 * @property string UNIDADE_COMPLEMENTO
 * @property string UNIDADE_TELEFONE
 * @property int UNIDADE_ATIVA
 * @property int UNIDADE_TIPO
 *
 */
class Unidade extends Model
{
    protected $table = "UNIDADE";
    protected $primaryKey = "UNIDADE_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "UNIDADE_NOME",
        "UNIDADE_CNES",
        "BAIRRO_ID",
        "UNIDADE_ENDERECO",
        "UNIDADE_COMPLEMENTO",
        "UNIDADE_TELEFONE",
        "UNIDADE_ATIVA",
        "UNIDADE_PORTE",
        "UNIDADE_TIPO"
    ];

    protected $casts = [
        'BAIRRO_ID' => 'integer',
        'UNIDADE_ATIVA' => 'integer',
        'UNIDADE_PORTE' => 'integer',
        'UNIDADE_TIPO' => 'integer',
    ];

    public static $relsearch = [
        "unidadeTipo",
        "unidadePorte",
        "bairro",
        "setores.setoresAtribuicoes.atribuicao"
    ];

    public function setores()
    {
        return $this->hasMany(Setor::class, "UNIDADE_ID", "UNIDADE_ID")
            ->where('SETOR_ATIVO', 1)
            ->orderByRaw("CASE WHEN SETOR_NOME = 'GERAL' THEN 0 ELSE 1 END")
            ->orderBy("SETOR_NOME");
    }

    public function unidadeTipo()
    {
        return $this->hasOne(TabelaGenerica::class, "COLUNA_ID", "UNIDADE_TIPO")
            ->where('TABELA_ID', RTG::TIPO_UNIDADE)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function lotacoes()
    {
        return $this->hasMany(Lotacao::class, "UNIDADE_ID", "UNIDADE_ID");
    }

    public function unidadePorte()
    {
        return $this->hasOne(TabelaGenerica::class, "COLUNA_ID", "UNIDADE_PORTE")
            ->where('TABELA_ID', RTG::UNIDADE_PORTE)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function bairro()
    {
        return $this->hasOne(Bairro::class, "BAIRRO_ID", "BAIRRO_ID");
    }

    public function setoresAtribuicoes()
    {
        return $this->hasManyThrough(
            SetorAtribuicao::class,
            Setor::class,
            'UNIDADE_ID',
            'SETOR_ID',
            'UNIDADE_ID',
            'SETOR_ID'
        )
            ->join('ATRIBUICAO', 'ATRIBUICAO.ATRIBUICAO_ID', '=', 'SETOR_ATRIBUICAO.ATRIBUICAO_ID')
            ->orderBy('SETOR.SETOR_NOME')
            ->orderBy('ATRIBUICAO.ATRIBUICAO_NOME');
    }

    public static function relacionamento()
    {
        return [
            "setores.setoresAtribuicoes.atribuicao",
            "setoresAtribuicoes.setor",
            "setoresAtribuicoes.atribuicao",
            "unidadeTipo",
            "unidadePorte",
            "bairro",
            "bairro",
        ];
    }

    public static function listar($requisicao)
    {
        $user = Auth::user();
        $ehPerfilDev = $user->usuarioPerfis()->where('PERFIL_ID', PerfilEnum::DESENVOLVEDOR)->exists();
        return self::with(self::relacionamento())
            ->when($requisicao->UNIDADE_NOME, function (Builder $query) use ($requisicao) {
                return $query->where("UNIDADE_NOME", "like", "%" . $requisicao->UNIDADE_NOME . "%");
            })
            ->when($requisicao->UNIDADE_CNES, function (Builder $query) use ($requisicao) {
                return $query->where("UNIDADE_CNES", "like", "%" . $requisicao->UNIDADE_CNES . "%");
            })
            ->when($requisicao->UNIDADE_ENDERECO, function (Builder $query) use ($requisicao) {
                return $query->where("UNIDADE_ENDERECO", "like", "%" . $requisicao->UNIDADE_ENDERECO . "%");
            })
            ->when($requisicao->UNIDADE_BAIRRO, function (Builder $query) use ($requisicao) {
                return $query->where("UNIDADE_BAIRRO", "like", "%" . $requisicao->UNIDADE_BAIRRO . "%");
            })
            ->when($requisicao->UNIDADE_COMPLEMENTO, function (Builder $query) use ($requisicao) {
                return $query->where("UNIDADE_COMPLEMENTO", "like", "%" . $requisicao->UNIDADE_COMPLEMENTO . "%");
            })
            ->when($requisicao->UNIDADE_TELEFONE, function (Builder $query) use ($requisicao) {
                return $query->where("UNIDADE_TELEFONE", "like", "%" . $requisicao->UNIDADE_TELEFONE . "%");
            })
            ->when($requisicao->UNIDADE_TIPO, function (Builder $query) use ($requisicao) {
                return $query->where('UNIDADE_TIPO', '=', $requisicao->UNIDADE_TIPO);
            })
            ->when($requisicao->UNIDADE_PORTE, function (Builder $query) use ($requisicao) {
                return $query->where('UNIDADE_PORTE', $requisicao->UNIDADE_PORTE);
            })
            // ->where('UNIDADE_ATIVA', '=', 1)
            ->when($requisicao->orderBy, function (Builder $query) use ($requisicao) {
                $requisicao->sort = $requisicao->sort ?: 'asc';
                $query->orderBy($requisicao->orderBy, $requisicao->sort);
            })
            ->when(!$requisicao->orderBy, function (Builder $query) {
                $query->orderBy('UNIDADE_NOME');
            })
            ->when(!$ehPerfilDev, function (Builder $query) use ($user){
                $query->whereIn('UNIDADE_ID', $user->usuarioUnidades->pluck('UNIDADE_ID'));
            })
            ->paginate();
    }

    public static function pesquisar($requisicao)
    {
        $user = Auth::user();
        $ehPerfilDev = $user->usuarioPerfis()->where('PERFIL_ID', PerfilEnum::DESENVOLVEDOR)->exists();
        return self::with(self::relacionamento())
            ->when($requisicao->UNIDADE_NOME, function (Builder $query) use ($requisicao) {
                return $query->where("UNIDADE_NOME", "like", "%" . $requisicao->UNIDADE_NOME . "%");
            })
            ->when($requisicao->UNIDADE_CNES, function (Builder $query) use ($requisicao) {
                return $query->where("UNIDADE_CNES", "like", "%" . $requisicao->UNIDADE_CNES . "%");
            })
            ->when($requisicao->UNIDADE_ENDERECO, function (Builder $query) use ($requisicao) {
                return $query->where("UNIDADE_ENDERECO", "like", "%" . $requisicao->UNIDADE_ENDERECO . "%");
            })
            ->when($requisicao->UNIDADE_BAIRRO, function (Builder $query) use ($requisicao) {
                return $query->where("UNIDADE_BAIRRO", "like", "%" . $requisicao->UNIDADE_BAIRRO . "%");
            })
            ->when($requisicao->UNIDADE_COMPLEMENTO, function (Builder $query) use ($requisicao) {
                return $query->where("UNIDADE_COMPLEMENTO", "like", "%" . $requisicao->UNIDADE_COMPLEMENTO . "%");
            })
            ->when($requisicao->UNIDADE_TELEFONE, function (Builder $query) use ($requisicao) {
                return $query->where("UNIDADE_TELEFONE", "like", "%" . $requisicao->UNIDADE_TELEFONE . "%");
            })
            ->when($requisicao->UNIDADE_TIPO, function (Builder $query) use ($requisicao) {
                return $query->where('UNIDADE_TIPO', '=', $requisicao->UNIDADE_TIPO);
            })
            ->when(!$ehPerfilDev, function (Builder $query) use ($user){
                $query->whereIn('UNIDADE_ID', $user->usuarioUnidades->pluck('UNIDADE_ID'));
            })
            ->where('UNIDADE_ATIVA', 1)
            ->orderBy('UNIDADE_NOME')
            ->get();
    }

    public static function buscar($id)
    {
        return self::with(self::relacionamento())
            ->find($id);
    }

    public static function detalhes()
    {
        $unidades = self::with(self::relacionamento())
            ->where('UNIDADE_ATIVA', '=', 1)
            ->orderBy('UNIDADE_NOME')
            ->get();

        $gestores = Lotacao::gestao();

        return [
            'unidades' => $unidades,
            'gestores' => $gestores
        ];
    }

    public static function search($valorPesquisa)
    {
        $user = Auth::user();
        $ehPerfilDev = $user->usuarioPerfis()->where('PERFIL_ID', PerfilEnum::DESENVOLVEDOR)->exists();
        return self::with(self::$relsearch)
        ->where(function ($query) use ($valorPesquisa) {
            $query->where("UNIDADE_NOME", "like", "%$valorPesquisa%")
                ->orWhere("UNIDADE_CNES", "$valorPesquisa")
                ->orWhere("UNIDADE_ENDERECO", "like", "%$valorPesquisa%")
                ->orWhere("UNIDADE_COMPLEMENTO", "like", "%$valorPesquisa%")
                ->orWhere("UNIDADE_TELEFONE", "like", "%$valorPesquisa%");
        })
        ->where("UNIDADE_ATIVA", 1)
        ->when(!$ehPerfilDev, function (Builder $query) use ($user) {
            $query->whereIn('UNIDADE_ID', $user->usuarioUnidades->pluck('UNIDADE_ID'));
        })
        ->paginate(10);
    }

    public static function all($columns = ['*'])
    {
        return static::query()
            ->orderBy('UNIDADE_NOME', 'ASC')
            ->get(
                is_array($columns) ? $columns : func_get_args()
            );
    }

    public static function listAll($soAtivas = 1)
    {
        return self::with(self::relacionamento())
            ->where('UNIDADE_ATIVA', '=', $soAtivas == null ? 1 : $soAtivas)
            ->orderBy('UNIDADE_NOME')
            ->get();
    }

    public static function getDadosRelatorioImprimirUnidade($unidadeId, $setorId = null, $atribuicaoId = null)
    {
        $whereSetor     = $setorId ? "AND S.SETOR_ID = $setorId" : "";
        $whereAtrib     = $atribuicaoId ? "AND A.ATRIBUICAO_ID = $atribuicaoId" : "";

        $whereSetorSub  = $setorId ? "AND S2.SETOR_ID = $setorId" : "";
        $whereAtribSub  = $atribuicaoId ? "AND A2.ATRIBUICAO_ID = $atribuicaoId" : "";

        $sql = "
            SELECT 
                F.FUNCIONARIO_MATRICULA AS MATRICULA,
                P.PESSOA_NOME AS NOME,
                P.PESSOA_CPF_NUMERO AS CPF,
                F.FUNCIONARIO_DATA_INICIO AS ADMISSAO,
                S.SETOR_NOME AS SETOR,
                A.ATRIBUICAO_NOME AS CARGO,
                AL.ATRIBUICAO_LOTACAO_CARGA_HORARIA AS CARGA_HORARIA,
                U.USUARIO_NOME AS USUARIO_CADASTRADOR,
                UN.UNIDADE_NOME,

                (
                    SELECT COUNT(*) 
                    FROM FUNCIONARIO F2
                    INNER JOIN PESSOA P2 ON F2.PESSOA_ID = P2.PESSOA_ID
                    INNER JOIN LOTACAO L2 ON F2.FUNCIONARIO_ID = L2.FUNCIONARIO_ID
                    INNER JOIN SETOR S2 ON L2.SETOR_ID = S2.SETOR_ID
                    INNER JOIN ATRIBUICAO_LOTACAO AL2 ON L2.LOTACAO_ID = AL2.LOTACAO_ID
                    INNER JOIN ATRIBUICAO A2 ON AL2.ATRIBUICAO_ID = A2.ATRIBUICAO_ID
                    LEFT JOIN USUARIO U2 ON F2.USUARIO_ID = U2.USUARIO_ID
                    INNER JOIN UNIDADE UN2 ON S2.UNIDADE_ID = UN2.UNIDADE_ID
                    WHERE UN2.UNIDADE_ID = $unidadeId
                    $whereSetorSub
                    $whereAtribSub
                ) AS TOTAL_REGISTROS

            FROM FUNCIONARIO F
            INNER JOIN PESSOA P ON F.PESSOA_ID = P.PESSOA_ID
            INNER JOIN LOTACAO L ON F.FUNCIONARIO_ID = L.FUNCIONARIO_ID
            INNER JOIN SETOR S ON L.SETOR_ID = S.SETOR_ID
            INNER JOIN ATRIBUICAO_LOTACAO AL ON L.LOTACAO_ID = AL.LOTACAO_ID
            INNER JOIN ATRIBUICAO A ON AL.ATRIBUICAO_ID = A.ATRIBUICAO_ID
            LEFT JOIN USUARIO U ON F.USUARIO_ID = U.USUARIO_ID
            INNER JOIN UNIDADE UN ON S.UNIDADE_ID = UN.UNIDADE_ID

            WHERE UN.UNIDADE_ID = $unidadeId
            $whereSetor
            $whereAtrib

            ORDER BY P.PESSOA_NOME ASC
        ";

        return DB::select(DB::raw($sql));
    }
}
