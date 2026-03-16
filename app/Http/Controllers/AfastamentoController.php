<?php

namespace App\Http\Controllers;

use App\Http\Requests\Afastamento\AfastamentoCreateRequest;
use App\Http\Requests\Afastamento\AfastamentoUpdateRequest;
use App\Models\Afastamento;
use App\MyLibs\PerfilEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AfastamentoController extends Controller
{
    private $label = 'Afastamento';

    public function view()
    {
        return view('home');
    }

    public function inserir(AfastamentoCreateRequest $request)
    {
        $afastamento = new Afastamento($request->input());
        $afastamento->save();

        return response(Afastamento::buscar($afastamento->AFASTAMENTO_ID), 200);
    }

    public function listar(Request $request)
    {
        $afastamento = Afastamento::listar($request)->paginate();

        return response([
            "cod" => 1,
            "msg" => "$this->label listado com sucesso",
            "retorno" => $afastamento
        ], 200);
    }

    public function buscar($id)
    {
        $afastamento = Afastamento::buscar($id);

        return response([
            "cod" => 1,
            "msg" => "$this->label id {$id} buscado com sucesso",
            "retorno" => $afastamento
        ], 200);
    }

    public function alterar(AfastamentoUpdateRequest $request)
    {
        $afastamento = Afastamento::buscar($request->AFASTAMENTO_ID);
        $afastamento->fill($request->post());
        $afastamento->update();

        return response(Afastamento::buscar($afastamento->AFASTAMENTO_ID), 200);
    }

    /**
     * GET /afastamento/alerta-expirar
     *
     * Retorna afastamentos ativos com data de fim próxima (até 60 dias)
     * ou já expirados, agrupados por urgência.
     *
     * COORD_DE_SETOR → apenas seu setor | demais → todos
     */
    public function alertaExpirar(Request $request)
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

        $dados = Afastamento::alertaExpirar($setorId);

        return response()->json([
            'cod' => 1,
            'msg' => 'Alerta de afastamentos a expirar carregado com sucesso',
            'retorno' => $dados,
        ]);
    }
}
