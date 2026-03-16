<?php

namespace App\Http\Controllers;

use App\Http\Requests\Ferias\FeriasCreateRequest;
use App\Http\Requests\Ferias\FeriasUpdateRequest;
use App\Models\Ferias;
use App\MyLibs\PerfilEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeriasController extends Controller
{
    private $label = 'Férias';

    public function view()
    {
        return view('home');
    }

    public function inserir(FeriasCreateRequest $request)
    {
        $ferias = new Ferias($request->input());
        $ferias->save();

        return response(Ferias::buscar($ferias->FERIAS_ID), 200);
    }

    public function listar(Request $request)
    {
        $ferias = Ferias::listar($request)->paginate();

        return response([
            "cod" => 1,
            "msg" => "$this->label listado com sucesso",
            "retorno" => $ferias
        ], 200);
    }

    public function buscar(Request $request)
    {
        $ferias = Ferias::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Ferias id {$request->id} buscado com sucesso",
            "retorno" => $ferias
        ], 200);
    }

    public function alterar(FeriasUpdateRequest $request)
    {
        $ferias = Ferias::buscar($request->FERIAS_ID);
        $ferias->fill($request->post());
        $ferias->update();

        return response(Ferias::buscar($request->FERIAS_ID), 200);
    }

    /**
     * GET /ferias/alerta-vencer
     *
     * Retorna funcionários com período aquisitivo de férias vencido ou
     * próximo de vencer (até 120 dias), sem férias de gozo marcadas.
     *
     * Acesso:
     *   - COORD_DE_SETOR → apenas funcionários do próprio setor
     *   - Demais perfis  → todos os setores
     */
    public function alertaVencer(Request $request)
    {
        /** @var \App\Models\Usuario $usuario */
        $usuario = Auth::user();
        $perfil = (int) $usuario->PERFIL_ID;

        $setorId = null;

        if ($perfil === PerfilEnum::COORD_DE_SETOR) {
            $funcionario = $usuario->funcionario;
            if ($funcionario) {
                $lotacaoAtiva = $funcionario->lotacoes()
                    ->where(function ($q) {
                        $q->whereNull('LOTACAO_DATA_FIM')
                            ->orWhere('LOTACAO_DATA_FIM', '>=', now());
                    })
                    ->first();
                $setorId = $lotacaoAtiva?->SETOR_ID;
            }
        }

        $dados = Ferias::alertaVencer($setorId);

        return response()->json([
            'cod' => 1,
            'msg' => 'Alerta de férias a vencer carregado com sucesso',
            'retorno' => $dados,
        ]);
    }
}
