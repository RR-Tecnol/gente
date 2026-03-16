<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Comentario;
use Carbon\Carbon;
use App\Http\Requests\Comentario\ComentarioCreateRequest;

class ComentarioController extends Controller
{
    public function listar(Request $request){
        $comentario = Comentario::with(['usuario'])
        ->when($request->PESSOA_ID, function(Builder $query) use($request){
            $query->where('PESSOA_ID', $request->PESSOA_ID);
        })
        ->orderBy('COMENTARIO_DATA_CRIACAO','desc')
        ->get();
        return response($comentario);
    }

    public function inserir(ComentarioCreateRequest $request){
        $comentario = new Comentario($request->input());
        $comentario->COMENTARIO_DATA_CRIACAO = Carbon::now();
        $comentario->save();
        return response($comentario);
    }

    public function alterar(Request $request){
        $comentario = Comentario::find($request->COMENTARIO_ID);
        //$comentario->fill($request->input());
        $comentario->USUARIO_ID = $request->user()->USUARIO_ID;
        $comentario->COMENTARIO_DATA_CONCLUSAO = Carbon::now();
        $comentario->update();
        return response($comentario);
    }
}
