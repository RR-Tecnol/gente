<?php

namespace App\Models;

use App\MyLibs\RTG;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 *@property integer ATRIBUICAO_LOTACAO_ID
 *@property integer ATRIBUICAO_ID
 *@property integer LOTACAO_ID
 *@property integer ATRIBUICAO_LOTACAO_CARGA_HORARIA
 *@property string ATRIBUICAO_LOTACAO_INICIO
 *@property string ATRIBUICAO_LOTACAO_FIM
 * @method static AtribuicaoLotacao find(mixed $ATRIBUICAO_LOTACAO_ID)
 */
class AtribuicaoLotacao extends Model
{
    use HasFactory;

    protected $table = "ATRIBUICAO_LOTACAO";
    protected $primaryKey = "ATRIBUICAO_LOTACAO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "ATRIBUICAO_ID",
        "LOTACAO_ID",
        "ATRIBUICAO_LOTACAO_CARGA_HORARIA",
        "ATRIBUICAO_LOTACAO_INICIO",
        "ATRIBUICAO_LOTACAO_FIM",
        "ATRIBUICAO_LOTACAO_VALOR",
        "TIPO_CALCULO_ID",
        "PROGRAMA_ID",
    ];
    protected $casts = [
        "ATRIBUICAO_LOTACAO_ID" => "integer",
        "ATRIBUICAO_ID" => "integer",
        "LOTACAO_ID" => "integer",
        "ATRIBUICAO_LOTACAO_CARGA_HORARIA" => "integer",
        "TIPO_CALCULO_ID" => "integer",
        "PROGRAMA_ID" => "integer",
    ];

    protected static $relacionamentos = [
        "atribuicao",
        "lotacao.funcionario.pessoa.cpf",
        "lotacao.vinculo",
        "lotacao.setor.unidade",
        "atribuicaoLotacaoEventos.evento.incidencia",
        "atribuicaoLotacaoEventos.evento.historicoEvento.formaCalculo",
        "tipoCalculos",
        "programa"
    ];

    public function atribuicao()
    {
        return $this->hasOne(Atribuicao::class, "ATRIBUICAO_ID", "ATRIBUICAO_ID");
    }

    public function lotacao()
    {
        return $this->hasOne(Lotacao::class, "LOTACAO_ID", "LOTACAO_ID");
    }

    public function atribuicaoLotacaoEventos()
    {
        return $this->hasMany(AtribuicaoLotacaoEvento::class, 'ATRIBUICAO_LOTACAO_ID', 'ATRIBUICAO_LOTACAO_ID');
    }

    public function atribuicaoLotacaoCargaHoraria()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "ATRIBUICAO_LOTACAO_CARGA_HORARIA")
            ->where("TABELA_ID", "=", RTG::ATRIBUICAO_LOTACAO_CARGA_HORARIA)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function tipoCalculos()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "TIPO_CALCULO_ID")
            ->where("TABELA_ID", "=", RTG::TIPO_CALCULO)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function programa()
    {
        return $this->hasOne(Programa::class, "PROGRAMA_ID", "PROGRAMA_ID");
    }

    public static function search($valorPesquisa)
    {
        return self::with(self::$relacionamentos)
            ->whereHas("lotacao", function (Builder $query) use ($valorPesquisa) {
                $query->whereHas("vinculo", function (Builder $query) use ($valorPesquisa) {
                    $query->when($valorPesquisa->VINCULO_ID, function ($query) use ($valorPesquisa) {
                        $query->where('VINCULO_ID', $valorPesquisa->VINCULO_ID);
                    });
                })
                    ->whereHas("setor", function (Builder $query) use ($valorPesquisa) {
                        $query->when($valorPesquisa->SETOR_ID, function ($query) use ($valorPesquisa) {
                            $query->where('SETOR_ID', $valorPesquisa->SETOR_ID);
                        });
                    })
                    ->whereHas("funcionario.pessoa", function (Builder $query) use ($valorPesquisa) {
                        $query->when($valorPesquisa->PESSOA_NOME, function ($query) use ($valorPesquisa) {
                            $query->where('PESSOA_NOME', 'like', "%$valorPesquisa->PESSOA_NOME%");
                        });
                    });
            })
            ->whereHas("atribuicao", function (Builder $query) use ($valorPesquisa) {
                $query->when($valorPesquisa->ATRIBUICAO_ID, function ($query) use ($valorPesquisa) {
                    $query->where('ATRIBUICAO_ID', $valorPesquisa->ATRIBUICAO_ID);
                });
            })
            ->paginate(10);
    }
}
