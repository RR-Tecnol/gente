<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class HistAtribuicaoConfig extends Model
{
    use HasFactory;
    protected $table = 'HIST_ATRIBUICAO_CONFIG';
    protected $primaryKey = "HIST_ATRIBUICAO_CONFIG_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "HIST_ATRIBUICAO_CONFIG_INICIO",
        "HIST_ATRIBUICAO_CONFIG_FIM",
        "HIST_ATRIBUICAO_CONFIG_VALOR",
        "HIST_ATRIBUICAO_CONFIG_EXTRA",
        "ATRIBUICAO_CONFIG_ID",
        "USUARIO_ID"
    ];

    protected $casts = [
        "ATRIBUICAO_CONFIG_ID" => "integer",
        "USUARIO_ID" => "integer"
    ];

    public function usuario()
    {
        return $this->hasOne(Usuario::class, 'USUARIO_ID', 'USUARIO_ID');
    }

    public static function addHistoricoConfig(AtribuicaoConfig $atribuicaoConfig, $valor, $extra)
    {
        $configs = self::where('ATRIBUICAO_CONFIG_ID', $atribuicaoConfig->ATRIBUICAO_CONFIG_ID)
            ->whereNull('HIST_ATRIBUICAO_CONFIG_FIM')
            ->get();
        foreach ($configs as $config) {
            $config->HIST_ATRIBUICAO_CONFIG_FIM = date('Y-m-d H:i:s');
            $config->save();
        }

        self::create([
            "HIST_ATRIBUICAO_CONFIG_INICIO" => date('Y-m-d H:i:s'),
            // "HIST_ATRIBUICAO_CONFIG_FIM",
            "HIST_ATRIBUICAO_CONFIG_VALOR" => $valor,
            "HIST_ATRIBUICAO_CONFIG_EXTRA" => $extra,
            "ATRIBUICAO_CONFIG_ID" => $atribuicaoConfig->ATRIBUICAO_CONFIG_ID,
            "USUARIO_ID" => Auth::id()
        ]);
    }
}
