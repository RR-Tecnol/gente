<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int DOSSIE_ID
 * @property int LOTACAO_ID
 * @property int USUARIO_ID
 * @property string DOSSIE_DT_OCORRENCIA
 * @property string DOSSIE_DT_CADASTRO
 * @property string DOSSIE_DT_OBSERVAÇÃO
 *
 */
class Dossie extends Model
{
    protected $table = "DOSSIE";
    protected $primaryKey = "DOSSIE_ID";
    public $timestamps = false;
    protected $fillable = [
        "LOTACAO_ID",
        "USUARIO_ID",
        "DOSSIE_DT_OCORRENCIA",
        "DOSSIE_DT_CADASTRO",
        "DOSSIE_OBSERVACAO"
    ];

    public function usuario()
    {
        return $this->hasOne(Usuario::class, "USUARIO_ID", "USUARIO_ID");
    }

    public function lotacao()
    {
        return $this->hasOne(Lotacao::class, "LOTACAO_ID", "LOTACAO_ID");
    }

    public static function relacionamento()
    {
        return [
            "usuario",
            "lotacao.unidade",
            "lotacao.cargo",
            "lotacao.funcionario.pessoa",
        ];
    }

    public static function listar()
    {
        return self::with(self::relacionamento())
            ->orderBy('DOSSIE_DT_CADASTRO', 'desc')
            ->paginate();
    }

    public static function pesquisar($requisacao)
    {
        return self::with(self::relacionamento())
            ->when($requisacao->DOSSIE_DT_OCORRENCIA, function (Builder $query) use ($requisacao) {
                return $query->where("DOSSIE_DT_OCORRENCIA", "=", $requisacao->DOSSIE_DT_OCORRENCIA);
            })
            ->when($requisacao->DOSSIE_DT_OBSERVAÇÃO, function (Builder $query) use ($requisacao) {
                return $query->where("DOSSIE_DT_OBSERVAÇÃO", "like", "%" . $requisacao->DOSSIE_DT_OBSERVAÇÃO . "%");
            })
            ->orderBy('DOSSIE_DT_CADASTRO', 'desc')
            ->get();
    }

    public static function buscar($id)
    {
        return self::with(self::relacionamento())
            ->find($id);
    }
}
