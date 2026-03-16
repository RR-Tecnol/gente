<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property integer PESSOA_CONSELHO_ID
 * @property integer PESSOA_ID
 * @property integer CONSELHO_ID
 * @property integer UF_ID
 * @property integer PESSOA_CONSELHO_NUMERO
 * @method static PessoaConselho find(array|string|null $post)
 */
class PessoaConselho extends Model
{
    use HasFactory;

    protected $table = "PESSOA_CONSELHO";
    protected $primaryKey = "PESSOA_CONSELHO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "PESSOA_ID",
        "CONSELHO_ID",
        "UF_ID",
        "PESSOA_CONSELHO_NUMERO",
    ];
    protected $casts = [
        "PESSOA_ID" => "integer",
        "CONSELHO_ID" => "integer",
        "UF_ID" => "integer",
        "PESSOA_CONSELHO_NUMERO" => "integer",
    ];

    public function conselho()
    {
        return $this->hasOne(Conselho::class, "CONSELHO_ID", "CONSELHO_ID");
    }

    public function uf()
    {
        return $this->hasOne(Uf::class, "UF_ID", "UF_ID");
    }
}
