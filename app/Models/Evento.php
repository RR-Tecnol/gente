<?php

namespace App\Models;

use App\MyLibs\RTG;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    use HasFactory;
    protected $table = "EVENTO";
    protected $primaryKey = "EVENTO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "EVENTO_DESCRICAO",
        "EVENTO_SALARIO",
        "EVENTO_IMPOSTO",
        "EVENTO_INCIDENCIA",
        "EVENTO_SISTEMA",
        "EVENTO_ATIVO"
    ];

    protected $casts = [
        "EVENTO_SALARIO" => 'integer',
        "EVENTO_IMPOSTO" => 'integer',
        "EVENTO_INCIDENCIA" => 'integer',
        "EVENTO_INTERNO" => 'integer',
        "EVENTO_SISTEMA" => 'integer',
        "EVENTO_ATIVO" => 'integer',
    ];

    public function incidencia()
    {
        return $this->hasOne(TabelaGenerica::class, 'COLUNA_ID', 'EVENTO_INCIDENCIA')
            ->where('TABELA_ID', RTG::TIPO_INCIDENCIA);
    }

    public function tributacoes()
    {
        return $this->hasMany(Tributacao::class, 'EVENTO_ID_PROVENTO', 'EVENTO_ID');
    }

    public function eventosVinculos()
    {
        return $this->hasMany(EventoVinculo::class, 'EVENTO_ID', 'EVENTO_ID');
    }

    public function historicoEventos()
    {
        return $this->hasMany(HistoricoEvento::class, 'EVENTO_ID', 'EVENTO_ID')
            ->where('HISTORICO_EVENTO_EXCLUIDO', 0);
    }

    public function historicoEvento()
    {
        return $this->hasOne(HistoricoEvento::class, 'EVENTO_ID', 'EVENTO_ID')
            ->where(function ($query) {
                $query->where('HISTORICO_EVENTO_FIM', '>=', Carbon::now()->format('Ym'))
                    ->orWhereNull('HISTORICO_EVENTO_FIM');
            })
            ->where('HISTORICO_EVENTO_INICIO', '<=', Carbon::now()->format('Ym'))

            ->where('HISTORICO_EVENTO_EXCLUIDO', 0);
    }

    public function vigenciaImpostos()
    {
        return $this->hasMany(VigenciaImposto::class, 'EVENTO_ID', 'EVENTO_ID');
    }

    public static $relacionamento = [
        'incidencia',
        'tributacoes.eventoImposto',
        'tributacoes.vinculo',
        'eventosVinculos.vinculo',
        'historicoEventos.formaCalculo',
        'vigenciaImpostos.tabelaImpostos',
        'historicoEvento.formaCalculo',
    ];

    public static function buscar($id)
    {
        return self::with(self::$relacionamento)
            ->find($id);
    }

    public static function listar()
    {
        return self::with(self::$relacionamento);
    }
}
