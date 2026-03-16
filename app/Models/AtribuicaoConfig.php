<?php

namespace App\Models;

use App\MyLibs\RTG;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AtribuicaoConfig extends Model
{
    use HasFactory;

    protected $table = "ATRIBUICAO_CONFIG";
    protected $primaryKey = "ATRIBUICAO_CONFIG_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "ATRIBUICAO_CONFIG_CARGA_HORARIA",
        "ATRIBUICAO_CONFIG_PORTE_UNIDADE",
        "ATRIBUICAO_ID",
        "ATRIBUICAO_CONFIG_ATIVA",
    ];
    protected $casts = [
        "ATRIBUICAO_CONFIG_ID" => "integer",
        "ATRIBUICAO_CONFIG_CARGA_HORARIA" => "integer",
        "ATRIBUICAO_CONFIG_PORTE_UNIDADE" => "integer",
        "ATRIBUICAO_ID" => "integer",
        "ATRIBUICAO_CONFIG_ATIVA" => "integer",
    ];

    public static $relacionamentos = [
        "atribuicaoConfigPorteUnidade",
        "atribuicao",
    ];

    public function atribuicaoConfigPorteUnidade()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "ATRIBUICAO_CONFIG_PORTE_UNIDADE")
            ->where("TABELA_ID", "=", RTG::UNIDADE_PORTE)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function atribuicao()
    {
        return $this->hasOne(Atribuicao::class, "ATRIBUICAO_ID", "ATRIBUICAO_ID");
    }

    public function histAtribuicaoConfig()
    {
        return $this->hasMany(HistAtribuicaoConfig::class, 'ATRIBUICAO_CONFIG_ID', 'ATRIBUICAO_CONFIG_ID')
            ->orderBy('HIST_ATRIBUICAO_CONFIG_ID', 'DESC');
    }
}
