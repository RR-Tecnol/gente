<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioUnidade extends Model
{
    protected $table = "USUARIO_UNIDADE";
    protected $primaryKey = "USUARIO_UNIDADE_ID";
    public $timestamps = false;
    protected $fillable = [
        "UNIDADE_ID",
        "USUARIO_ID",
        "USUARIO_UNIDADE_FISCAL",
        "USUARIO_UNIDADE_ATIVO",
    ];

    protected $casts = [
        "USUARIO_UNIDADE_ID" => "integer",
        "UNIDADE_ID" => "integer",
        "USUARIO_ID" => "integer",
        "USUARIO_UNIDADE_FISCAL" => "integer",
        "USUARIO_UNIDADE_ATIVO" => "integer",
    ];

    public function usuario()
    {
        return $this->hasOne(Usuario::class, "USUARIO_ID", "USUARIO_ID");
    }

    public function unidade()
    {
        return $this->hasOne(Unidade::class, "UNIDADE_ID", "UNIDADE_ID");
    }

    public static function relacionamento()
    {
        return [
            "usuario",
            "unidade.setores"
        ];
    }

    public static function listar($request)
    {
        if ($request->id)
            return self::with(self::relacionamento())
                ->where('USUARIO_ID', '=', $request->id)->get();
        else if ($request->idUsuario)
            return self::with(self::relacionamento())
                ->where('UNIDADE_ID', '=', $request->idUsuario)->get();
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
