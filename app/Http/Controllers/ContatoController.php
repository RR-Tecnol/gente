<?php

namespace App\Http\Controllers;

use App\Http\Requests\Contato\ContatoCreateRequest;
use App\Http\Requests\Contato\ContatoDeleteRequest;
use App\Http\Requests\Contato\ContatoUpdateRequest;
use App\Mail\UsuarioMail;
use App\Models\Contato;
use App\Models\Pessoa;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ContatoController extends Controller
{
    public function create(ContatoCreateRequest $request)
    {
        try {
            DB::beginTransaction();
            $contato = new Contato($request->input());
            $contato->save();
            Pessoa::atualizarStatus($contato->PESSOA_ID);

            // if($contato->CONTATO_TIPO == 2){
            //     $pessoa= Pessoa::find($contato->PESSOA_ID);
            //     if($pessoa->funcionario && $pessoa->funcionario->usuario){
            //         Mail::to($contato->CONTATO_CONTEUDO)->send(new UsuarioMail($pessoa->funcionario->usuario));
            //     }

            // }
            DB::commit();
            return response(Pessoa::buscar($contato->PESSOA_ID));
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function update(ContatoUpdateRequest $request)
    {
        try {
            DB::beginTransaction();
            $contato = Contato::find($request->input("CONTATO_ID"));
            $contato->fill($request->input());
            $contato->update();
            Pessoa::atualizarStatus($contato->PESSOA_ID);
            DB::commit();
            return response(Pessoa::buscar($contato->PESSOA_ID));
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function listar(Request $request)
    {
        $contato = Contato::listar($request);

        return response([
            "cod" => 1,
            "msg" => "Contato listado com sucesso",
            "retorno" => $contato
        ], 200);
    }

    public function delete(ContatoDeleteRequest $request)
    {
        try {
            Contato::find($request->input("CONTATO_ID"))->delete();
            return response(Pessoa::buscar($request->input("PESSOA_ID")));
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function buscar(Request $request)
    {
        $contato = Contato::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Contato id {$request->id} buscado com sucesso",
            "retorno" => $contato
        ], 200);
    }

    public function alterar(ContatoUpdateRequest $request)
    {
        $contato = Contato::buscar($request->CONTATO_ID);
        $contato->fill($request->post());
        $contato->update();

        return response([
            "cod" => 1,
            "msg" => "Contato id {$request->CONTATO_ID} alterado com sucesso",
            "retorno" => $contato
        ], 200);
    }
}
