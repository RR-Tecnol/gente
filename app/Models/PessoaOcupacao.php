<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property integer PESSOA_OCUPACAO_ID
 * @property integer PESSOA_ID
 * @property integer OCUPACAO_ID
 * @property integer PESSOA_OCUPACAO_PRINCIPAL
 * @method static PessoaOcupacao find(array|string|null $post)
 */
class PessoaOcupacao extends Model
{
    use HasFactory;

    protected $table = "PESSOA_OCUPACAO";
    protected $primaryKey = "PESSOA_OCUPACAO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "PESSOA_ID",
        "OCUPACAO_ID",
        "PESSOA_OCUPACAO_PRINCIPAL",
    ];
    protected $casts = [
        "PESSOA_ID" => "integer",
        "OCUPACAO_ID" => "integer",
        "PESSOA_OCUPACAO_PRINCIPAL" => "integer",
    ];

    public function pessoa()
    {
        return $this->hasOne(Pessoa::class, "PESSOA_ID", "PESSOA_ID");
    }

    public function ocupacao()
    {
        return $this->hasOne(Ocupacao::class, "OCUPACAO_ID", "OCUPACAO_ID");
    }
}
