<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tributacao\TributacaoCreateRequest;
use App\Http\Requests\Tributacao\TributacaoDeleteRequest;
use App\Http\Requests\Tributacao\TributacaoUpdateRequest;
use App\Models\Evento;
use App\Models\Tributacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TributacaoController extends Controller
{
    public function inserir(TributacaoCreateRequest $request)
    {
        DB::beginTransaction();
        foreach ($request->EVENTO_ID_IMPOSTO as $impostoId) {
            foreach ($request->VINCULO_ID as $vinculoId) {
                $tributacao = new Tributacao($request->input());
                $tributacao->EVENTO_ID_IMPOSTO = $impostoId;
                $tributacao->VINCULO_ID = $vinculoId;
                $tributacao->save();
            }
        }
        DB::commit();

        $evento = Evento::buscar($request->EVENTO_ID_PROVENTO);
        return response($evento, 200);
    }

    public function listar(Request $request)
    {
        $tributacao = Tributacao::listar($request)->paginate();

        return response($tributacao, 200);
    }

    public function buscar(Request $request)
    {
        $tributacao = Tributacao::buscar($request->id);

        return response($tributacao, 200);
    }

    public function alterar(TributacaoUpdateRequest $request)
    {
        $tributacao = Tributacao::buscar($request->TRIBUTACAO_ID);
        $tributacao->fill($request->post());
        $tributacao->update();

        $evento = Evento::buscar($request->EVENTO_ID_PROVENTO);
        return response($evento, 200);
    }

    public function deletar(TributacaoDeleteRequest $request)
    {
        $tributacao = Tributacao::buscar($request->TRIBUTACAO_ID);
        if ($tributacao->TRIBUTACAO_ATIVA == 1) {
            $tributacao->TRIBUTACAO_ATIVA = 0;
        } else {
            $tributacao->TRIBUTACAO_ATIVA = 1;
        }
        $tributacao->save();

        $evento = Evento::buscar($request->EVENTO_ID_PROVENTO);
        return response($evento, 200);
    }
}
