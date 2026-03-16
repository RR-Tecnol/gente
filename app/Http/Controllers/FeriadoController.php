<?php

namespace App\Http\Controllers;

use App\Http\Requests\Feriado\FeriadoCreateRequest;
use App\Http\Requests\Feriado\FeriadoUpdateRequest;
use App\Models\Feriado;
use App\Models\TabelaGenerica;
use App\Services\FeriadoService;
use Illuminate\Http\Request;

class FeriadoController extends Controller
{
    private $label = 'Feriado';
    private $feriadoService;

    public function __construct(FeriadoService $feriadoService)
    {
        $this->feriadoService = $feriadoService;
    }


    public function view()
    {
        $tipo_feriados = TabelaGenerica::tipo_feriado();
        return view('feriado.feriado_view', compact('tipo_feriados'));
    }

    public function inserir(FeriadoCreateRequest $request)
    {
        $feriado = new Feriado($request->input());
        $feriado->save();
        return response([
            'retorno' => $feriado,
            'msg' => "$this->label inserido com sucesso",
        ], 200);
    }

    public function listar(Request $request)
    {
        $feriado = Feriado::listar($request)->paginate();

        return response([
            'retorno' => $feriado,
            'msg' => "$this->label listado com sucesso",
        ], 200);
    }

    public function buscar($id)
    {
        $feriado = Feriado::buscar($id);

        return response([
            'retorno' => $feriado,
            'msg' => "$this->label buscado com sucesso",
        ], 200);
    }

    public function alterar(FeriadoUpdateRequest $request)
    {
        $feriado = Feriado::find($request->FERIADO_ID);
        $feriado->fill($request->input());
        $feriado->update();

        return response([
            'retorno' => $feriado,
            'msg' => "$this->label alterado com sucesso",
        ], 200);
    }

    public function buscarFeriado($data)
    {
        $data = $this->feriadoService->getFeriado($data);
        return response($data, 200);
    }

    public function buscarTodosPorAno($ano)
    {
        $feriados = $this->feriadoService->getFeriadosAno($ano);
        return response($feriados);
    }

    public function buscarEntreDatas($dataInicial, $dataFinal)
    {
        $feriados = $this->feriadoService->getEntreDatas($dataInicial, $dataFinal);
        return response($feriados);
    }

    public function buscarProximoFeriado($data)
    {
        $feriados = $this->feriadoService->getProximoFeriado($data);
        return response($feriados);
    }

    public function buscarFeriadoAnterior($data)
    {
        $feriados = $this->feriadoService->getFeriadoAnterior($data);
        return response($feriados);
    }

    public function buscarFeriadoMesAno($mesAno)
    {
        $feriados = $this->feriadoService->getFeriadoMesAno($mesAno);
        return response($feriados);
    }

    public function buscarCalendario($mesAno)
    {
        $calendario = $this->feriadoService->getCalendario($mesAno);
        return response($calendario, 200, ["Content-Type" => "application/json"]);
    }
}
