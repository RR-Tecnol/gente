<?php

namespace App\Models;

use App\Casts\Cnpj;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Programa extends Model
{
    protected $table = "PROGRAMA";
    protected $primaryKey = "PROGRAMA_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "DESCRICAO",
        "CNPJ",
        "BANCO_ID",
        "FONTE_RECURSO_ID",
        "COD_CONVENIO",
        "CEP",
        "COMPLEMENTO",
        "NUMERO",
        "ENDERECO",
        "AGENCIA",
        "AGENCIA_DV",
        "CONTA_CORRENTE",
        "CONTA_CORRENTE_DV",
        "COD_PROGRAMA",
        "ATIVO",
    ];

    protected $casts = [
        "PROGRAMA_ID" => "integer",
        "CNPJ" => Cnpj::class,
        "BANCO_ID" => "integer",
        "FONTE_RECURSO_ID" => "integer",
        "ATIVO" => "integer",
    ];

    public static function relacionamento()
    {
        return [
            "banco",
            "fonteRecurso",
        ];
    }

    public function banco()
    {
        return $this->hasOne(Banco::class, 'BANCO_ID', 'BANCO_ID');
    }

    public function fonteRecurso()
    {
        return $this->hasOne(FonteRecurso::class, 'FONTE_RECURSO_ID', 'FONTE_RECURSO_ID');
    }

    public static function listar($request)
    {
        return self::with(self::relacionamento())
            ->when($request->DESCRICAO, function (Builder $query) use ($request) {
                $query->where('DESCRICAO', 'like', "%$request->DESCRICAO%");
            })
            ->when($request->CNPJ, function (Builder $query) use ($request) {
                $query->where('CNPJ', 'like', "%$request->CNPJ%");
            })
            ->when($request->BANCO_ID, function (Builder $query) use ($request) {
                $query->where('BANCO_ID', $request->BANCO_ID);
            })
            ->when($request->FONTE_RECURSO_ID, function (Builder $query) use ($request) {
                $query->where('FONTE_RECURSO_ID', $request->FONTE_RECURSO_ID);
            })
            ->when($request->COD_CONVENIO, function (Builder $query) use ($request) {
                $query->where('COD_CONVENIO', 'like', "%$request->COD_CONVENIO%");
            })
            ->when($request->CEP, function (Builder $query) use ($request) {
                $query->where('CEP', 'like', "%$request->CEP%");
            })
            ->when($request->ENDERECO, function (Builder $query) use ($request) {
                $query->where('ENDERECO', 'like', "%$request->ENDERECO%");
            })
            ->when($request->NUMERO, function (Builder $query) use ($request) {
                $query->where('NUMERO', 'like', "%$request->NUMERO%");
            })
            ->when($request->COMPLEMENTO, function (Builder $query) use ($request) {
                $query->where('COMPLEMENTO', 'like', "%$request->COMPLEMENTO%");
            })
            ->when($request->AGENCIA, function (Builder $query) use ($request) {
                $query->where('AGENCIA', 'like', "%$request->AGENCIA%");
            })
            ->when($request->AGENCIA_DV, function (Builder $query) use ($request) {
                $query->where('AGENCIA_DV', 'like', "%$request->AGENCIA_DV%");
            })
            ->when($request->CONTA_CORRENTE, function (Builder $query) use ($request) {
                $query->where('CONTA_CORRENTE', 'like', "%$request->CONTA_CORRENTE%");
            })
            ->when($request->CONTA_CORRENTE_DV, function (Builder $query) use ($request) {
                $query->where('CONTA_CORRENTE_DV', 'like', "%$request->CONTA_CORRENTE_DV%");
            })
            ->when($request->COD_PROGRAMA, function (Builder $query) use ($request) {
                $query->where('COD_PROGRAMA', 'like', "%$request->COD_PROGRAMA%");
            })
            ->when($request->orderBy, function (Builder $query) use ($request) {
                $request->sort = $request->sort ?: 'asc';
                $query->orderBy($request->orderBy, $request->sort);
            });
    }

    public static function buscar($id)
    {
        return self::with(self::relacionamento())
            ->find($id);
    }
}
