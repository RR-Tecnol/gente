<?php

namespace App\Http\Controllers;

use App\Http\Requests\PessoaProfissao\PessoaProfissaoCreateRequest;
use App\Http\Requests\PessoaProfissao\PessoaProfissaoUpdateRequest;
use App\Models\PessoaProfissao;
use Illuminate\Http\Request;

class PessoaProfissaoController extends Controller
{
    public function inserir(PessoaProfissaoCreateRequest $request)
    {
        $pessoaprofissao = new PessoaProfissao($request->input());
        $pessoaprofissao->PESSOA_PROFISSAO_ATIVA = 1;
        $pessoaprofissao->save();

        return response([
            "cod" => 1,
            "msg" => "PessoaProfissao adicionado com sucesso",
            "retorno" => $pessoaprofissao
        ], 200);
    }

    public function listar(Request $request)
    {
        $pessoaprofissoes = PessoaProfissao::listar($request);

        return response([
            "cod" => 1,
            "msg" => "PessoaProfissao listado com sucesso",
            "retorno" => $pessoaprofissoes
        ], 200);
    }

    public function buscar(Request $request)
    {
        $pessoaprofissao = PessoaProfissao::buscar($request->id);
        $pessoaprofissao->profissao;
        return response([
            "cod" => 1,
            "msg" => "PessoaProfissao id {$request->id} buscado com sucesso",
            "retorno" => $pessoaprofissao
        ], 200);
    }

    public function deletar(Request $request)
    {
        $pessoaprofissao = PessoaProfissao::buscar($request->id);
        $pessoaprofissao->PESSOA_PROFISSAO_ATIVA = 0;
        $pessoaprofissao->save();

        return response([
            "cod" => 1,
            "msg" => "PessoaProfissao id {$request->id} deletado com sucesso",
            "retorno" => $pessoaprofissao
        ], 200);
    }

    public function alterar(PessoaProfissaoUpdateRequest $request)
    {
        $pessoaprofissao = PessoaProfissao::buscar($request->PESSOA_PROFISSAO_ID);
        $pessoaprofissao->fill($request->post());
        $pessoaprofissao->update();

        return response([
            "cod" => 1,
            "msg" => "PessoaProfissao id {$request->PESSOA_PROFISSAO_ID} alterado com sucesso",
            "retorno" => $pessoaprofissao
        ], 200);
    }
}
