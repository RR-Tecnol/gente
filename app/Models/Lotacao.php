<?php

namespace App\Models;

use App\MyLibs\RTG;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property int LOTACAO_ID
 * @property int FUNCIONARIO_ID
 * @property int VINCULO_ID
 * @property int SETOR_ID
 * @property string LOTACAO_DATA_INICIO
 * @property string LOTACAO_DATA_FIM
 * @property int LOTACAO_TIPO_FIM
 * @property string LOTACAO_OBSERVACAO
 *
 * @method static Lotacao find($id)
 */
class Lotacao extends Model
{
    protected $table = "LOTACAO";
    protected $primaryKey = "LOTACAO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "FUNCIONARIO_ID",
        "VINCULO_ID",
        "SETOR_ID",
        "LOTACAO_DATA_INICIO",
        "LOTACAO_DATA_FIM",
        "LOTACAO_TIPO_FIM",
        "LOTACAO_OBSERVACAO",
        "LOTACAO_DESVIO_FUNCAO",
    ];

    protected $casts = [
        "LOTACAO_ID" => "integer",
        "FUNCIONARIO_ID" => "integer",
        "VINCULO_ID" => "integer",
        "SETOR_ID" => "integer",
        "LOTACAO_TIPO_FIM" => "integer",
        "LOTACAO_DESVIO_FUNCAO" => "integer"
    ];

    public static $relacionamentos = [
        "vinculo",
        "setor.unidade.unidadePorte",
        "atribuicaoLotacoes.atribuicao.atribuicaoEscolaridade",
        "atribuicaoLotacoes.atribuicao.atribuicaoTipo",
        "atribuicaoLotacoes.atribuicaoLotacaoCargaHoraria",
        "funcionario.pessoa.cpf",
        "lotacoesEventos.evento.incidencia",
        "lotacoesEventos.evento.historicoEvento.formaCalculo",
    ];

    public static $relacionamentos2 = [
        "vinculo",
        "setor.unidade.unidadePorte",
        "atribuicaoLotacoes.atribuicao.atribuicaoEscolaridade",
        "atribuicaoLotacoes.atribuicao.atribuicaoTipo",
        "atribuicaoLotacoes.atribuicaoLotacaoCargaHoraria",
        "funcionario.pessoa.cpf",
    ];

    public function atribuicaoLotacoes()
    {
        return $this->hasMany(AtribuicaoLotacao::class, "LOTACAO_ID", "LOTACAO_ID");
    }

    public function atribuicoes()
    {
        return $this->hasManyThrough(Atribuicao::class, AtribuicaoLotacao::class, 'LOTACAO_ID', 'ATRIBUICAO_ID', 'LOTACAO_ID', 'ATRIBUICAO_ID');
    }

    public function funcionario()
    {
        return $this->hasOne(Funcionario::class, "FUNCIONARIO_ID", "FUNCIONARIO_ID");
    }

    public function vinculo()
    {
        return $this->hasOne(Vinculo::class, "VINCULO_ID", "VINCULO_ID");
    }

    public function setor()
    {
        return $this->hasOne(Setor::class, "SETOR_ID", "SETOR_ID");
    }

    public function lotacoesEventos()
    {
        return $this->hasMany(LotacaoEvento::class, 'LOTACAO_ID', 'LOTACAO_ID');
    }

    public function lotacaoTipoFim()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "LOTACAO_TIPO_FIM")
            ->where("TABELA_ID", "=", RTG::LOTACAO_TIPO_FIM)
            ->where("COLUNA_ID", "!=", 0);
    }


    public static function listar($request)
    {
        if ($request->id)
            return self::with(self::$relacionamentos)
                ->where("FUNCIONARIO_ID", "=", $request->id)->get();
        else
            return self::with(self::$relacionamentos)
                ->all();
    }

    public static function pesquisar($requisicao)
    {
        return self::with(self::$relacionamentos)
            ->when($requisicao->LOTACAO_ID, function (Builder $query) use ($requisicao) {
                return $query->where("LOTACAO_ID", $requisicao->LOTACAO_ID);
            })
            ->when($requisicao->SETOR_ID, function (Builder $query) use ($requisicao) {
                return $query->where("SETOR_ID",  $requisicao->SETOR_ID);
            })
            ->when($requisicao->VINCULO_ID, function (Builder $query) use ($requisicao) {
                return $query->where("VINCULO_ID", $requisicao->VINCULO_ID);
            })
            ->when($requisicao->ATRIBUICAO_ID, function (Builder $query) use ($requisicao) {
                $query->whereHas('atribuicaoLotacoes', function (Builder $query) use ($requisicao) {
                    $query->where('ATRIBUICAO_ID', $requisicao->ATRIBUICAO_ID);
                });
            })

            ->when($requisicao->PESSOA_NOME, function (Builder $query) use ($requisicao) {
                $query->whereHas('funcionario.pessoa', function (Builder $query) use ($requisicao) {
                    $query->where('PESSOA_NOME', "like", "%$requisicao->PESSOA_NOME%");
                });
            })
            ->when($requisicao->FUNCIONARIO_ID, function (Builder $query) use ($requisicao) {
                return $query->where("FUNCIONARIO_ID", $requisicao->FUNCIONARIO_ID);
            })
            ->paginate(10);
    }

    public static function buscar($id)
    {
        return self::with(self::$relacionamentos)
            ->find($id);
    }

    public static function getById($id)
    {
        return self::with(self::$relacionamentos)
            ->find($id);
    }

    public static function gestao()
    {
        // return DB::select(
        // DB::raw('
        // SELECT
        //     L.*,
        //     C.*,
        //     F.*,
        //     P.*
        // FROM LOTACAO L
        // JOIN CARGO C on C.CARGO_ID = L.CARGO_ID
        // JOIN FUNCIONARIO F ON F.FUNCIONARIO_ID = L.FUNCIONARIO_ID
        // JOIN PESSOA P ON P.PESSOA_ID = F.PESSOA_ID
        // WHERE c.CARGO_GESTAO = 1
        // AND L.LOTACAO_DATA_FIM IS NULL
        // OR GETDATE() < L.LOTACAO_DATA_FIM;
        // '));

        return self::with(self::relacionamento())
            ->whereHas('cargo', function (Builder $query) {
                $query->where('CARGO_GESTAO', "=", 1);
            })
            ->where("LOTACAO_DATA_FIM", ">=", date("m-d-Y"))
            ->orWhereNull('LOTACAO_DATA_FIM')
            ->get();
    }

    public static function getBySetor($setorId, $rel = null)
    {
        return self::with($rel == null ? self::$relacionamentos2 : $rel)->where("SETOR_ID", $setorId)->get();
    }

    public static function getDadosRelatorioImprimirLotacao($lotacaoId)
    {
        $sql = "
        SELECT
            S.SETOR_NOME,
            U.UNIDADE_NOME,
            P.PESSOA_NOME,
            A.ATRIBUICAO_NOME,
            V.VINCULO_SIGLA,
            L.LOTACAO_DATA_INICIO,
            P.PESSOA_RG_NUMERO AS RG,
            P.PESSOA_CPF_NUMERO AS CPF,
            P.PESSOA_ENDERECO,
            P.PESSOA_COMPLEMENTO,
            B.BAIRRO_NOME,
            C.CIDADE_NOME,
            CEL.CONTATO_CONTEUDO AS CELULAR,
            TEL.CONTATO_CONTEUDO AS TEL,
            EMAIL.CONTATO_CONTEUDO AS EMAIL

        FROM LOTACAO L
        INNER JOIN SETOR S ON S.SETOR_ID = L.SETOR_ID
        INNER JOIN UNIDADE U ON U.UNIDADE_ID = S.UNIDADE_ID
        INNER JOIN FUNCIONARIO F ON F.FUNCIONARIO_ID = L.FUNCIONARIO_ID
        INNER JOIN PESSOA P ON P.PESSOA_ID = F.PESSOA_ID
        LEFT JOIN BAIRRO B ON B.BAIRRO_ID = P.BAIRRO_ID
        LEFT JOIN CIDADE C ON C.CIDADE_ID = P.CIDADE_ID
        INNER JOIN ATRIBUICAO_LOTACAO AL ON AL.LOTACAO_ID = L.LOTACAO_ID
        INNER JOIN ATRIBUICAO A ON A.ATRIBUICAO_ID = AL.ATRIBUICAO_ID
        INNER JOIN VINCULO V ON V.VINCULO_ID = L.VINCULO_ID

        LEFT JOIN CONTATO CEL ON CEL.PESSOA_ID = P.PESSOA_ID AND CEL.CONTATO_TIPO = 3
        LEFT JOIN CONTATO TEL ON TEL.PESSOA_ID = P.PESSOA_ID AND TEL.CONTATO_TIPO = 1
        LEFT JOIN CONTATO EMAIL ON EMAIL.PESSOA_ID = P.PESSOA_ID AND EMAIL.CONTATO_TIPO = 2

        WHERE L.LOTACAO_ID = $lotacaoId
    ";

        return DB::select(DB::raw($sql));
    }
}
