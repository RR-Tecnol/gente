<?php

namespace App\Models;

use App\MyLibs\RTG;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @property int TABELA_GENERICA_ID
 * @property int TABELA_ID
 * @property int COLUNA_ID
 * @property string DESCRICAO
 * @property int ATIVO
 * @method static TabelaGenerica find(array|string|null $post)
 */
class TabelaGenerica extends Model
{
    protected $table = "TABELA_GENERICA";
    protected $primaryKey = "TABELA_GENERICA_ID";
    public $timestamps = false;
    protected $fillable = [
        "TABELA_ID",
        "COLUNA_ID",
        "DESCRICAO",
        "ATIVO",
    ];

    protected $casts = [
        "TABELA_ID" => "integer",
        "COLUNA_ID" => "integer",
        "ATIVO" => "integer"
    ];

    public function tabela_generica()
    {
        return $this->hasOne(TabelaGenerica::class, "TABELA_GENERICA_ID", "TABELA_ID");
    }

    public function tabela()
    {
        return $this->hasOne(TabelaGenerica::class, "TABELA_ID", "TABELA_ID")
            ->where("COLUNA_ID", 0);
    }

    public static function relacionamento()
    {
        return [
            "tabela_generica"
        ];
    }

    public static function listarTabelas()
    {
        return self::with([])->where("COLUNA_ID", 0)->get();
    }

    public static function listarColunasTabela($tabelaId, $somenteAtivos = 0, $with = [], $campoOrderBy = "COLUNA_ID", $direcao = "asc")
    {
        return self::with($with)
            ->where("TABELA_ID", "=", $tabelaId)
            ->where("COLUNA_ID", "!=", 0)
            ->when($somenteAtivos == 1, function ($q) {
                return $q->where("ATIVO", 1);
            })
            ->when($campoOrderBy, function ($q) use ($campoOrderBy, $direcao) {
                return $q->orderBy($campoOrderBy, $direcao);
            })->get();
    }

    public static function getColunaId($tabelaId, $colunaId)
    {
        return self::where("TABELA_ID", "=", $tabelaId)
            ->where("TABELA_GENERICA_ID", "=", $colunaId)
            ->get();
    }

    public static function listar()
    {
        return self::with(self::relacionamento())
            ->paginate();
    }

    public static function pesquisar($requisicao)
    {
        return self::with(self::relacionamento())
            ->when($requisicao->DESCRICAO, function (Builder $query) use ($requisicao) {
                return $query->where("DESCRICAO", "like", "%$requisicao->DESCRICAO%");
            })
            ->when($requisicao->TABELA_ID, function (Builder $query) use ($requisicao) {
                return $query->where("TABELA_ID", "=", $requisicao->TABELA_ID);
            })
            ->when($requisicao->ATIVO, function (Builder $query) use ($requisicao) {
                return $query->where("ATIVO", "=", $requisicao->ATIVO);
            })
            ->get();
    }

    public static function buscar($id)
    {
        return self::with(self::relacionamento())
            ->find($id);
    }

    public static function obterUltimoIdDeTabela()
    {
        return DB::table("TABELA_GENERICA")
            ->select([DB::raw("MAX(TABELA_ID) AS TABELA_ID")])
            ->where("COLUNA_ID", 0)
            ->pluck("TABELA_ID")
            ->first();
    }

    public static function obterUltimoIdDeColuna($tabelaId)
    {
        return DB::table("TABELA_GENERICA")
            ->select([DB::raw("MAX(COLUNA_ID) AS COLUNA_ID")])
            ->where("TABELA_ID", $tabelaId)
            ->where("COLUNA_ID", ">", 0)
            ->pluck("COLUNA_ID")
            ->first();
    }

    // TABELAS

    public static function tabelaGenerica($colunaId = null)
    {
        $tabela = RTG::TABELA_GENERICA;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function escolaridade($colunaId = null)
    {
        $tabela = RTG::ESCOLARIDADE;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function sexo($colunaId = null)
    {
        $tabela = RTG::SEXO;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function contato($colunaId = null)
    {
        $tabela = RTG::CONTATO_TIPO;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function documento($colunaId = null)
    {
        $tabela = RTG::DOCUMENTO;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function tipoAfastamento($colunaId = null)
    {
        $tabela = RTG::TIPO_AFASTAMENTO;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function historico($colunaId = null)
    {
        $tabela = RTG::HISTORICO;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function motivo($colunaId = null)
    {
        $tabela = RTG::MOTIVO;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function status($colunaId = null)
    {
        $tabela = RTG::STATUS;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function progresso($colunaId = null)
    {
        $tabela = RTG::PROGRESSO;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function tipo_unidade($colunaId = null)
    {
        $tabela = RTG::TIPO_UNIDADE;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function unidadePorte($colunaId = null)
    {
        $tabela = RTG::UNIDADE_PORTE;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function tipo_depedente($colunaId = null)
    {
        $tabela = RTG::TIPO_DEPENDENTE;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function tipo_finalizacao_dependente($colunaId = null)
    {
        $tabela = RTG::TIPO_FINALIZACAO_DEPENDENTE;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function tipo_conselho($colunaId = null)
    {
        $tabela = RTG::TIPO_CONSELHO_CLASSE;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function tipo_status_escala($colunaId = null)
    {
        $tabela = RTG::TIPO_STATUS_ESCALA;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function tipo_feriado($colunaId = null)
    {
        $tabela = RTG::TIPO_FERIADO;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function status_folha($colunaId = null)
    {
        $tabela = RTG::STATUS_FOLHA;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function tipo_folha($colunaId = null)
    {
        $tabela = RTG::TIPOS_FOLHA;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function tipo_parametro_financeiro($colunaId = null)
    {
        $tabela = RTG::TIPO_PARAMETRO_FINANCEIRO;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function tipo_escala($colunaId = null)
    {
        $tabela = RTG::TIPO_ESCALA;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function tipo_calculo($colunaId = null)
    {
        $tabela = RTG::TIPO_CALCULO;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }

    public static function intervalo($colunaId = null)
    {
        $tabela = RTG::INTERVALO;
        if ($colunaId)
            return self::getColunaId($tabela, $colunaId);
        return self::listarColunasTabela($tabela);
    }
}
