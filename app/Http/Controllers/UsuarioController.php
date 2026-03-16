<?php

namespace App\Http\Controllers;

use App\Http\Requests\Usuario\UsuarioCreateRequest;
use App\Http\Requests\Usuario\UsuarioUpdatePasswordRequest;
use App\Http\Requests\Usuario\UsuarioUpdateRequest;
use App\Models\Unidade;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UsuarioController extends Controller
{
    public function view()
    {
        $unidades = Unidade::where('UNIDADE_ATIVA', 1)->orderBy('UNIDADE_NOME')->get();

        return view('usuario.usuario_view', compact('unidades'));
    }

    public function alterarSenhaView()
    {
        return view('usuario.alteracao_senha');
    }

    public function inserir(UsuarioCreateRequest $request)
    {
        $usuario = new Usuario($request->input());
        $usuario->USUARIO_ATIVO = 1;
        $usuario->USUARIO_SENHA = md5($request->USUARIO_SENHA);
        $usuario->save();

        return response([
            "cod" => 1,
            "msg" => "Usuário adicionado com sucesso",
            "retorno" => Usuario::buscar($usuario->USUARIO_ID)
        ], 200);
    }

    public function listar(Request $request)
    {
        $usuario = Usuario::listar($request)->paginate();

        return response([
            "cod" => 1,
            "msg" => "Usuário listado com sucesso",
            "retorno" => $usuario
        ], 200);
    }

    public function buscar($id)
    {
        $usuario = Usuario::buscar($id);

        return response([
            "cod" => 1,
            "msg" => "Usuário id {$id} buscado com sucesso",
            "retorno" => $usuario
        ], 200);
    }

    public function alterar(UsuarioUpdateRequest $request)
    {
        $usuario = Usuario::buscar($request->USUARIO_ID);
        $senha_original = $usuario->USUARIO_SENHA;
        $usuario->fill($request->post());
        if ($request->USUARIO_SENHA == null || $request->USUARIO_SENHA == '') {
            $usuario->USUARIO_SENHA = $senha_original;
        } else {
            $usuario->USUARIO_SENHA = md5($request->USUARIO_SENHA);
        }
        $usuario->update();

        return response([
            "cod" => 1,
            "msg" => "Usuário id {$request->USUARIO_ID} alterado com sucesso",
            "retorno" => $usuario
        ], 200);
    }

    public function alterarSenha(UsuarioUpdatePasswordRequest $request)
    {
        $usuario = Auth::user();
        $usuario->USUARIO_SENHA = md5($request->USUARIO_SENHA);
        // USUARIO_ALTERAR_SENHA e USUARIO_PRIMEIRO_ACESSO são flags integer (0/1)
        // não aceitar Carbon::now() como valor
        $usuario->USUARIO_ALTERAR_SENHA = 1;
        if (!$usuario->USUARIO_PRIMEIRO_ACESSO) {
            $usuario->USUARIO_PRIMEIRO_ACESSO = 1;
        }
        $usuario->update();

        return response([
            "cod" => 1,
            "msg" => "Senha alterada com sucesso",
            "retorno" => $usuario
        ], 200);
    }
}
