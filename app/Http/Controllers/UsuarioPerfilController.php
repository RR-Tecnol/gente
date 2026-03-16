<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioPerfil\UsuarioPerfilCreateRequest;
use App\Http\Requests\UsuarioPerfil\UsuarioPerfilUpdateRequest;
use App\Models\Usuario;
use App\Models\UsuarioPerfil;
use Illuminate\Http\Request;

class UsuarioPerfilController extends Controller
{
    public function inserir(UsuarioPerfilCreateRequest $request)
    {
        $usuarioPerfil = new UsuarioPerfil($request->input());
        $usuarioPerfil->save();

        return response([
            "cod" => 1,
            "msg" => "Acesso adicionado com sucesso",
            "retorno" => Usuario::buscar($request->USUARIO_ID)
        ], 200);
    }

    public function listar(Request $request)
    {
        $usuarioPerfil = UsuarioPerfil::listar($request);

        return response([
            "cod" => 1,
            "msg" => "Acesso listado com sucesso",
            "retorno" => $usuarioPerfil
        ], 200);
    }

    public function alterar(UsuarioPerfilUpdateRequest $request)
    {
        $usuarioPerfil = UsuarioPerfil::buscar($request->USUARIO_PERFIL_ID);
        $usuarioPerfil->fill($request->post());
        $usuarioPerfil->update();

        return response([
            "cod" => 1,
            "msg" => "Acesso id {$request->USUARIO_PERFIL_ID} alterado com sucesso",
            "retorno" => Usuario::buscar($request->USUARIO_ID)
        ], 200);
    }

    public function deletar(Request $request)
    {
        $usuarioPerfil = UsuarioPerfil::buscar($request->USUARIO_PERFIL_ID);
        $usuarioPerfil->delete();

        return response([
            "cod" => 1,
            "msg" => "Acesso id {$request->USUARIO_PERFIL_ID} alterado com sucesso",
            "retorno" => Usuario::buscar($request->USUARIO_ID)
        ], 200);
    }
}
