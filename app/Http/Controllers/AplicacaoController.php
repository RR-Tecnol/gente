<?php

namespace App\Http\Controllers;

use App\Http\Requests\Aplicacao\AplicacaoCreateRequest;
use App\Http\Requests\Aplicacao\AplicacaoUpdateRequest;
use App\Models\Aplicacao;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AplicacaoController extends Controller
{
    public function view()
    {
        return view('aplicacao.aplicacao_view');
    }

    public function list()
    {
        return response(Aplicacao::listAll());
    }

    public function create(AplicacaoCreateRequest $request)
    {
        $aplicacao = new Aplicacao($request->input());
        $aplicacao->save();
        return response(Aplicacao::listAll());
    }

    public function update(AplicacaoUpdateRequest $request)
    {
        $aplicacao = Aplicacao::find($request->APLICACAO_ID);
        $aplicacao->fill($request->input());
        $aplicacao->update();
        return response(Aplicacao::listAll());
    }

    public function delete(Request $request)
    {
        try {
            DB::beginTransaction();
            Aplicacao::with([])->find($request->input("aplicacaoId"))->delete();
            DB::commit();
            return response(Aplicacao::listAll());
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }
}
