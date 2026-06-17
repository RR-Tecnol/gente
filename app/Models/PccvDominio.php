<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Planos de Cargos, Carreiras e Vencimentos (domínio jurídico para folha e classificação de cargo).
 *
 * @property int $PCCV_DOMINIO_ID
 * @property string $NOME_LEI
 * @property string|null $SIGLA
 * @property bool $ATIVO
 */
class PccvDominio extends Model
{
    protected $table = 'PCCV_DOMINIO';
    protected $primaryKey = 'PCCV_DOMINIO_ID';
    public $timestamps = false;
    public static $snakeAttributes = false;

    protected $fillable = [
        'NOME_LEI',
        'SIGLA',
        'ATIVO',
    ];

    protected $casts = [
        'ATIVO' => 'boolean',
    ];

    public function cargos(): HasMany
    {
        return $this->hasMany(Cargo::class, 'PCCV_ID', 'PCCV_DOMINIO_ID');
    }
}
