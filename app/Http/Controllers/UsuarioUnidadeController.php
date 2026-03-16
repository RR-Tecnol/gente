<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioUnidade\UsuarioUnidadeCreateRequest;
use App\Http\Requests\UsuarioUnidade\UsuarioUnidadeUpdateRequest;
use App\Models\Usuario;
use App\Models\UsuarioUnidade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UsuarioUnidadeController extends Controller
{
    public function inserir(UsuarioUnidadeCreateRequest $request)
    {
        DB::beginTransaction();
        foreach ($request->unidades as $unidade) {
            $usuarioUnidade = new UsuarioUnidade($unidade);
            $usuarioUnidade->UNIDADE_ID = $unidade['UNIDADE_ID'];
            $usuarioUnidade->USUARIO_ID = $request['USUARIO_ID'];
            $usuarioUnidade->USUARIO_UNIDADE_FISCAL = $request['USUARIO_UNIDADE_FISCAL'];
            $usuarioUnidade->USUARIO_UNIDADE_ATIVO = $request['USUARIO_UNIDADE_ATIVO'];
            $usuarioUnidade->save();
        }
        DB::commit();

        return response([
            "cod" => 1,
            "msg" => "UsuarioUnidade adicionado com sucesso",
            "retorno" => Usuario::buscar($request->USUARIO_ID)
        ], 200);
    }

    public function listar(Request $request)
    {
        $usuariounidade = UsuarioUnidade::listar($request);

        return response([
            "cod" => 1,
            "msg" => "UsuarioUnidade listado com sucesso",
            "retorno" => $usuariounidade
        ], 200);
    }

    public function alterar(UsuarioUnidadeUpdateRequest $request)
    {
        $usuariounidade = UsuarioUnidade::buscar($request->USUARIO_UNIDADE_ID);
        $usuariounidade->fill($request->post());
        $usuariounidade->update();

        return response([
            "cod" => 1,
            "msg" => "UsuarioUnidade id {$request->USUARIO_UNIDADE_ID} alterado com sucesso",
            "retorno" => Usuario::buscar($request->USUARIO_ID)
        ], 200);
    }
}
