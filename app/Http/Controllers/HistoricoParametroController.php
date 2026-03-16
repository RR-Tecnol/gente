<?php

namespace App\Http\Controllers;

use App\Http\Requests\HistoricoParametro\HistoricoParametroCreateRequest;
use App\Http\Requests\HistoricoParametro\HistoricoParametroDeleteRequest;
use App\Http\Requests\HistoricoParametro\HistoricoParametroUpdateRequest;
use App\Models\HistoricoParametro;
use App\Models\ParametroFinanceiro;
use Illuminate\Http\Request;

class HistoricoParametroController extends Controller
{
    public function inserir(HistoricoParametroCreateRequest $request)
    {
        $historicoParametro = new HistoricoParametro($request->input());

        if ($historicoParametro->HISTORICO_PARAMETRO_FIM == null) {
            $historicoParametro->HISTORICO_PARAMETRO_FIM = "12/2999";
        } else {
            $historicoParametro->HISTORICO_PARAMETRO_FIM = $request->HISTORICO_PARAMETRO_FIM;
        }

        $historicoParametro->save();

        $parametroFinceiro = ParametroFinanceiro::buscar($request->PARAMETRO_FINANCEIRO_ID);
        return response($parametroFinceiro, 200);
    }

    public function listar(Request $request)
    {
        $historicoParametro = HistoricoParametro::listar($request)->paginate();

        return response($historicoParametro, 200);
    }

    public function buscar(Request $request)
    {
        $historicoParametro = HistoricoParametro::buscar($request->id);

        return response($historicoParametro, 200);
    }

    public function alterar(HistoricoParametroUpdateRequest $request)
    {
        $historicoParametro = HistoricoParametro::buscar($request->HISTORICO_PARAMETRO_ID);
        $historicoParametro->fill($request->input());

        if ($historicoParametro->HISTORICO_PARAMETRO_FIM == "") {
            $historicoParametro->HISTORICO_PARAMETRO_FIM = "12/2999";
        } else {
            $historicoParametro->HISTORICO_PARAMETRO_FIM = $request->HISTORICO_PARAMETRO_FIM;
        }

        $historicoParametro->update();
        $parametroFinceiro = ParametroFinanceiro::buscar($request->PARAMETRO_FINANCEIRO_ID);
        return response($parametroFinceiro, 200);
    }

    public function deletar(HistoricoParametroDeleteRequest $request)
    {
        $historicoParametro = HistoricoParametro::buscar($request->HISTORICO_PARAMETRO_ID);
        $historicoParametro->delete();

        $parametroFinceiro = ParametroFinanceiro::buscar($request->PARAMETRO_FINANCEIRO_ID);
        return response($parametroFinceiro, 200);
    }
}
