<?php

namespace App\Models;

use App\Casts\Periodo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AtribuicaoLotacaoEvento extends Model
{
    use HasFactory;
    protected $table = "ATRIBUICAO_LOTACAO_EVENTO";
    protected $primaryKey = "ATRIBUICAO_LOTACAO_EVENTO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        'ATRIBUICAO_LOTACAO_ID',
        'EVENTO_ID',
        'ATRIBUICAO_LOTACAO_EVENTO_INFO',
        'ATRIBUICAO_LOTACAO_EVENTO_INICIO',
        'ATRIBUICAO_LOTACAO_EVENTO_FIM',
        'ATRIBUICAO_LOTACAO_EVENTO_VALOR',
        'ATRIBUICAO_LOTACAO_EVENTO_EXCLUIDO',
        'ATRIBUICAO_LOTACAO_EVENTO_DATA_CADASTRO',
        'USUARIO_ID',
    ];

    protected $casts = [
        "ATRIBUICAO_LOTACAO_ID" => 'integer',
        "EVENTO_ID" => 'integer',
        "ATRIBUICAO_LOTACAO_EVENTO_INICIO" => Periodo::class,
        "ATRIBUICAO_LOTACAO_EVENTO_FIM" => Periodo::class,
        "ATRIBUICAO_LOTACAO_EVENTO_EXCLUIDO" => 'integer',
        "USUARIO_ID" => 'integer'
    ];

    public static $relacionamento = [
        'atribuicaoLotacao.lotacao.setor.unidade',
        'atribuicaoLotacao.lotacao.funcionario.pessoa',
        'evento.incidencia',
        'evento.historicoEvento.formaCalculo',
        'usuario',
    ];

    public function atribuicaoLotacao()
    {
        return $this->hasOne(AtribuicaoLotacao::class, 'ATRIBUICAO_LOTACAO_ID', 'ATRIBUICAO_LOTACAO_ID');
    }

    public function evento()
    {
        return $this->hasOne(Evento::class, 'EVENTO_ID', 'EVENTO_ID');
    }

    public function usuario()
    {
        return $this->hasOne(Usuario::class, 'USUARIO_ID', 'USUARIO_ID');
    }

    public static function listar($request)
    {
        return self::with(self::$relacionamento)
            ->where('ATRIBUICAO_LOTACAO_EVENTO_EXCLUIDO', 0);
    }

    public static function buscar($id)
    {
        return self::with(self::$relacionamento)
            ->find($id);
    }
}
