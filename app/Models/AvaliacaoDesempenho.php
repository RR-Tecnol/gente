<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $AVALIACAO_ID
 * @property int $FUNCIONARIO_ID
 * @property string|null $AVALIACAO_CICLO
 * @property float $AVALIACAO_NOTA_FINAL
 * @property string $AVALIACAO_STATUS
 */
class AvaliacaoDesempenho extends Model
{
    protected $table = 'AVALIACAO_DESEMPENHO';

    protected $primaryKey = 'AVALIACAO_ID';

    public $timestamps = true;

    public static $snakeAttributes = false;

    protected $fillable = [
        'FUNCIONARIO_ID',
        'AVALIACAO_CICLO',
        'AVALIACAO_NOTA_FINAL',
        'AVALIACAO_STATUS',
        'AVALIADOR_ID',
        'AVALIACAO_OBS',
    ];

    protected $casts = [
        'FUNCIONARIO_ID' => 'integer',
        'AVALIACAO_NOTA_FINAL' => 'float',
    ];

    public function funcionario()
    {
        return $this->belongsTo(Funcionario::class, 'FUNCIONARIO_ID', 'FUNCIONARIO_ID');
    }
}
