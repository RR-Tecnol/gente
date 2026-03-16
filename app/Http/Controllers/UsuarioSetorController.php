<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\UsuarioSetor;
use Illuminate\Http\Request;

class UsuarioSetorController extends Controller
{
    public function inserir(Request $request){
        $request->validate([
            'SETOR_ID' => 'required|integer',
            'USUARIO_ID' => 'required|integer',
            'ATIVO' => 'required|integer',
        ]);
        $usuarioSetor = new UsuarioSetor($request->input());
        $usuarioSetor->save();
        $usuario = Usuario::buscar($usuarioSetor->USUARIO_ID);
        return response($usuario);
    }

    public function alterar(Request $request){
        $request->validate([
            'USUARIO_SETOR_ID' => 'required|integer',
            'SETOR_ID' => 'required|integer',
            'USUARIO_ID' => 'required|integer',
            'ATIVO' => 'required|integer',
        ]);
        $usuarioSetor = UsuarioSetor::find($request->input('USUARIO_SETOR_ID'));
        $usuarioSetor->fill($request->input());
        $usuarioSetor->save();
        $usuario = Usuario::buscar($usuarioSetor->USUARIO_ID);
        return response($usuario);
    }
}
