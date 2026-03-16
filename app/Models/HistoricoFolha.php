<?php

namespace App\Models;

use App\MyLibs\RTG;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class HistoricoFolha extends Model
{
    use HasFactory;
    protected $table = "HISTORICO_FOLHA";
    protected $primaryKey = "HISTORICO_FOLHA_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        'HISTORICO_FOLHA_STATUS',
        'HISTORICO_FOLHA_DATA',
        'USUARIO_ID',
        'FOLHA_ID',
        'HISTORICO_FOLHA_ERRO',
        'HISTORICO_FOLHA_ULTIMO'
    ];

    protected $casts = [
        'HISTORICO_FOLHA_STATUS' => 'integer',
        'USUARIO_ID' => 'integer',
        'FOLHA_ID' => 'integer',
        'HISTORICO_FOLHA_ULTIMO' => 'integer',
    ];

    public function statusFolha()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "HISTORICO_FOLHA_STATUS")
            ->where("TABELA_ID", "=", RTG::STATUS_FOLHA)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function usuario()
    {
        return $this->hasOne(Usuario::class, 'USUARIO_ID', 'USUARIO_ID');
    }

    public static function setHistorico(Folha $folha, $status = 1)
    {
        foreach ($folha->historicosFolhas as $historico) {
            if ($historico->HISTORICO_FOLHA_ULTIMO == 1) {
                $historico->HISTORICO_FOLHA_ULTIMO = 0;
                $historico->update();
            }
        }
        $historicoFolha = new HistoricoFolha();
        $historicoFolha->HISTORICO_FOLHA_STATUS = $status;
        $historicoFolha->HISTORICO_FOLHA_DATA = Carbon::now();
        $historicoFolha->USUARIO_ID = Auth::id();
        $historicoFolha->FOLHA_ID = $folha->FOLHA_ID;
        $historicoFolha->HISTORICO_FOLHA_ULTIMO = 1;
        $historicoFolha->save();
    }
}
