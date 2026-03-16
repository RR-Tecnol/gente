<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioPerfil extends Model
{
    protected $table = "USUARIO_PERFIL";
    protected $primaryKey = "USUARIO_PERFIL_ID";
    public $timestamps = false;
    protected $fillable = [
        "USUARIO_ID",
        "PERFIL_ID",
        "USUARIO_PERFIL_ATIVO",
    ];

    protected $casts = [
        "USUARIO_PERFIL_ID" => "integer",
        "USUARIO_ID" => "integer",
        "PERFIL_ID" => "integer",
        "USUARIO_PERFIL_ATIVO" => "integer"
    ];

    public function usuario()
    {
        return $this->hasOne(Usuario::class, 'USUARIO_ID', 'USUARIO_ID');
    }

    public function perfil()
    {
        return $this->hasOne(Perfil::class, 'PERFIL_ID', 'PERFIL_ID');
    }

    public function acessos()
    {
        return $this->hasMany(Acesso::class, "PERFIL_ID", "PERFIL_ID");
    }

    public static function relacionamento()
    {
        return [
            "usuario",
            "perfil"
        ];
    }

    public static function listar($request)
    {
        if ($request->id)
            return self::with(self::relacionamento())
                ->where('USUARIO_ID', $request->id)->get();
        else if ($request->idUsuario)
            return self::with(self::relacionamento())
                ->where('PERFIL_ID', $request->idUsuario)->get();
        else
            return self::with(self::relacionamento())
                ->all();
    }

    public static function buscar($id)
    {
        return self::with(self::relacionamento())
            ->find($id);
    }
}
