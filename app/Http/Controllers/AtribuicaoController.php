<?php

namespace App\Http\Controllers;

use App\Http\Requests\Atribuicao\AtribuicaoCreateRequest;
use App\Http\Requests\Atribuicao\AtribuicaoUpdateRequest;
use App\Models\Atribuicao;
use App\Models\TabelaGenerica;
use App\MyLibs\RTG;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AtribuicaoController extends Controller
{
    public function view()
    {
        $escolaridades = TabelaGenerica::listarColunasTabela(RTG::ESCOLARIDADE, 1);
        ;
        $tipoAtribuicoes = TabelaGenerica::listarColunasTabela(RTG::TIPOS_DE_ATRIBUICOES, 1);
        $porteUnidade = TabelaGenerica::listarColunasTabela(RTG::UNIDADE_PORTE, 1);

        return view('atribuicao.atribuicao_view', compact(
            'escolaridades',
            'tipoAtribuicoes',
            'porteUnidade'
        ));
    }

    public function search(Request $request)
    {
        return response(Atribuicao::search($request->input('valorPesquisa')));
    }

    public function create(AtribuicaoCreateRequest $request)
    {
        $atribuicao = new Atribuicao($request->input());
        $atribuicao->save();

        return response(Atribuicao::buscar($atribuicao->ATRIBUICAO_ID), 200);
    }

    public function update(AtribuicaoUpdateRequest $request)
    {
        $atribuicao = Atribuicao::find($request->ATRIBUICAO_ID);
        $atribuicao->fill($request->input());
        $atribuicao->update();

        return response(Atribuicao::buscar($atribuicao->ATRIBUICAO_ID), 200);
    }

    public function listar(Request $request)
    {
        $atribuicao = Atribuicao::listar($request)->paginate();

        return response($atribuicao, 200);
    }

    public function deletar(Request $request)
    {
        $atribuicao = Atribuicao::buscar($request->id);
        $atribuicao->ATRIBUICAO_USUARIO_EXCLUSAO = Auth::id();
        $atribuicao->save();
        $atribuicao->delete();

        return response($atribuicao, 200);
    }
}
