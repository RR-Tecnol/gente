<?php

namespace App\Http\Controllers;

use App\Http\Requests\ParametroFinanceiro\ParametroFinanceiroCreateRequest;
use App\Http\Requests\ParametroFinanceiro\ParametroFinanceiroUpdateRequest;
use App\Models\ParametroFinanceiro;
use App\Models\TabelaGenerica;
use App\MyLibs\RTG;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ParametroFinanceiroController extends Controller
{
    public function view()
    {
        $tiposHistoricos = TabelaGenerica::listarColunasTabela(RTG::TIPO_PARAMETRO_FINANCEIRO);

        return view('parametro_financeiro.parametro_financeiro', compact('tiposHistoricos'));
    }

    public function inserir(ParametroFinanceiroCreateRequest $request)
    {
        $parametro = new ParametroFinanceiro($request->input());
        $parametro->PARAMETRO_FINANCEIRO_DT_CADASTRO = Carbon::now();
        $parametro->USUARIO_ID = Auth::id();
        $parametro->save();

        return response($parametro, 200);
    }

    public function listar(Request $request)
    {
        $parametro = ParametroFinanceiro::listar($request)->paginate();

        return response($parametro, 200);
    }

    public function buscar($id)
    {
        $parametro = ParametroFinanceiro::buscar($id);

        return response($parametro, 200);
    }

    public function alterar(ParametroFinanceiroUpdateRequest $request)
    {
        $parametro = ParametroFinanceiro::find($request->PARAMETRO_FINANCEIRO_ID);
        $parametro->fill($request->input());
        $parametro->update();

        return response($parametro, 200);
    }
}
