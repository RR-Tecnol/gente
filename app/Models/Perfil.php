<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property integer PERFIL_ID
 * @property string PERFIL_NOME
 * @property integer PERFIL_ATIVO
 * @method static Perfil find($id)
 */
class Perfil extends Model
{
    use HasFactory;

    protected $table = "PERFIL";
    protected $primaryKey = "PERFIL_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "PERFIL_NOME",
        "PERFIL_ATIVO",
        "PERFIL_DASHBOARD_LINK"
    ];
    protected $casts = [
        "PERFIL_ID" => "integer",
        "PERFIL_ATIVO" => "integer",
    ];
    public static $rel1 = [
        "acessos.aplicacao.children",
    ];

    public function acessos()
    {
        return $this->hasMany(Acesso::class, "PERFIL_ID", "PERFIL_ID");
    }

    public static function getById($perfilId)
    {
        return self::with(self::$rel1)->find($perfilId);
    }

    public static function listAll()
    {
        return self::with(self::$rel1)->paginate();
    }

    public static function search($valorPesquisa, $soAtivos = 1)
    {
        return self::with(self::$rel1)
            ->where("PERFIL_NOME", "like", "%$valorPesquisa%")
            ->when($soAtivos == 1, function ($q) {
                $q->where("PERFIL_ATIVO", 1);
            });
    }
}
