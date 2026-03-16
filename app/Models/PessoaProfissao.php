<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PessoaProfissao extends Model
{
    protected $table = "PESSOA_PROFISSAO";
    protected $primaryKey = "PESSOA_PROFISSAO_ID";
    public $timestamps = false;
    protected $fillable = [
        "PESSOA_ID",
        "PROFISSAO_ID",
        "PESSOA_PROFISSAO_ATIVA",
    ];

    public function profissao()
    {
        return $this->hasOne(Profissao::class, "PROFISSAO_ID", "PROFISSAO_ID");
    }

    public function pessoa()
    {
        return $this->hasOne(Pessoa::class, "PESSOA_ID", "PESSOA_ID");
    }

    public static function relacionamento()
    {
        return [
            "profissao",
            "pessoa"
        ];
    }

    public static function listar($request)
    {
        if ($request->id)
            return self::with(self::relacionamento())
                ->where('PESSOA_ID', '=', $request->id)
                ->where('PESSOA_PROFISSAO_ATIVA', '=', 1)
                ->get();
        else if ($request->idPessoa)
            return self::with(self::relacionamento())
                ->where('PROFISSAO_ID', '=', $request->idPessoa)
                ->where('PESSOA_PROFISSAO_ATIVA', '=', 1)
                ->get();
        else
            return self::with(self::relacionamento())
                ->where('PESSOA_PROFISSAO_ATIVA', '=', 1)
                ->get();
    }

    public static function buscar($id)
    {
        return self::with(self::relacionamento())
            ->find($id);
    }
}
