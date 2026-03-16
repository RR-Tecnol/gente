<?php

namespace App\Models;

use App\Casts\Periodo;
use App\MyLibs\RTG;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class HistoricoEvento extends Model
{
    use HasFactory;
    protected $table = "HISTORICO_EVENTO";
    protected $primaryKey = "HISTORICO_EVENTO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "HISTORICO_EVENTO_INICIO",
        "HISTORICO_EVENTO_FIM",
        "HISTORICO_EVENTO_CALCULO",
        "HISTORICO_EVENTO_VALOR",
        "HISTORICO_EVENTO_EXCLUIDO",
        "EVENTO_ID",
        "USUARIO_ID",
    ];

    protected $casts = [
        'HISTORICO_EVENTO_CALCULO' => 'integer',
        'HISTORICO_EVENTO_INICIO' => Periodo::class,
        'HISTORICO_EVENTO_FIM' => Periodo::class,
    ];

    public function evento()
    {
        return $this->hasOne(Evento::class, 'EVENTO_ID', 'EVENTO_ID');
    }
    public function usuario()
    {
        return $this->hasOne(Usuario::class, 'USUARIO_ID', 'USUARIO_ID');
    }
    public function formaCalculo()
    {
        return $this->hasOne(TabelaGenerica::class, 'COLUNA_ID', 'HISTORICO_EVENTO_CALCULO')
            ->where('TABELA_ID', RTG::FORMA_CALCULO);
    }

    public static $relacionamento = [
        'evento',
        'usuario',
        'formaCalculo',
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
