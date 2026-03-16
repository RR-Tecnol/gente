<?php

namespace App\Http\Controllers;

use App\Http\Requests\AtribuicaoLotacao\AtribuicaoLotacaoCreateRequest;
use App\Models\AtribuicaoLotacao;
use App\Models\Funcionario;
use App\Models\Lotacao;
use Illuminate\Http\Request;

class AtribuicaoLotacaoController extends Controller
{
    public function create(AtribuicaoLotacaoCreateRequest $request)
    {
        $al = new AtribuicaoLotacao($request->input());
        $al->save();
        $lotacao = Lotacao::find($al->LOTACAO_ID);
        return response(Funcionario::buscar($lotacao->FUNCIONARIO_ID));
    }

    public function search(Request $request)
    {
        return response(AtribuicaoLotacao::search($request));
    }
}
