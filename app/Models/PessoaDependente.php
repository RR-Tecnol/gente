<?php

namespace App\Models;

use App\Casts\Cpf;
use Illuminate\Database\Eloquent\Model;

class PessoaDependente extends Model
{
    protected $table = 'PESSOA_DEPENDENTE';
    protected $primaryKey = 'PESSOA_DEPENDENTE_ID';
    public $timestamps = true;
    public static $snakeAttributes = false;

    protected $fillable = [
        'FUNCIONARIO_ID',
        'PESSOA_DEPENDENTE_NOME',
        'PESSOA_DEPENDENTE_CPF',
        'PESSOA_DEPENDENTE_NASCIMENTO',
        'PESSOA_DEPENDENTE_PARENTESCO',
        'PESSOA_DEPENDENTE_DEDUCAO_IRRF',
        'PESSOA_DEPENDENTE_SEXO',
        'PESSOA_DEPENDENTE_DT_INICIO',
        'PESSOA_DEPENDENTE_DT_FIM',
        'PESSOA_DEPENDENTE_MOTIVO_FIM',
    ];

    protected $casts = [
        'FUNCIONARIO_ID'                 => 'integer',
        'PESSOA_DEPENDENTE_DEDUCAO_IRRF' => 'integer',
        'PESSOA_DEPENDENTE_SEXO'         => 'integer',
        'PESSOA_DEPENDENTE_CPF'          => Cpf::class,
        'PESSOA_DEPENDENTE_NASCIMENTO'   => 'date:Y-m-d',
        'PESSOA_DEPENDENTE_DT_INICIO'    => 'date:Y-m-d',
        'PESSOA_DEPENDENTE_DT_FIM'       => 'date:Y-m-d',
    ];

    public function funcionario()
    {
        return $this->belongsTo(Funcionario::class, 'FUNCIONARIO_ID', 'FUNCIONARIO_ID');
    }
}
