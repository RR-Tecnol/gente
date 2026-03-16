<?php

namespace App\Http\Controllers;

use App\Http\Requests\evento\EventoCreateRequest;
use App\Http\Requests\evento\EventoDeleteRequest;
use App\Http\Requests\evento\EventoUpdateRequest;
use App\Models\Evento;
use App\Models\TabelaGenerica;
use App\Models\TabelaImposto;
use App\Models\Vinculo;
use App\MyLibs\RTG;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class EventoController extends Controller
{
    public function view()
    {
        $tipo_incidencias = TabelaGenerica::listarColunasTabela(RTG::TIPO_INCIDENCIA, 1);
        $forma_calculos = TabelaGenerica::listarColunasTabela(RTG::FORMA_CALCULO, 1);
        $impostos = Evento::listar()->where('EVENTO_ATIVO', 1)->where('EVENTO_IMPOSTO', 1)->get();
        $vinculos = Vinculo::listAll();

        return view('evento.evento_view', compact('tipo_incidencias', 'forma_calculos', 'impostos', 'vinculos'));
    }

    public function inserir(EventoCreateRequest $request)
    {
        $evento = new Evento($request->input());
        $evento->save();
        return response($evento, 200);
    }

    public function listar(Request $request)
    {
        $evento = Evento::listar($request)
            ->when($request->EVENTO_DESCRICAO, function (Builder $query) use ($request) {
                $query->where('EVENTO_DESCRICAO', 'like', "%$request->EVENTO_DESCRICAO%");
            })
            ->when($request->EVENTO_INCIDENCIA, function (Builder $query) use ($request) {
                $query->where('EVENTO_INCIDENCIA', $request->EVENTO_INCIDENCIA);
            })
            ->paginate();

        return response($evento, 200);
    }

    public function buscar(Request $request)
    {
        $evento = Evento::buscar($request->id);

        return response($evento, 200);
    }

    public function alterar(EventoUpdateRequest $request)
    {
        $evento = Evento::buscar($request->EVENTO_ID);
        $evento->fill($request->post());
        $evento->update();

        return response($evento, 200);
    }

    public function deletar(EventoDeleteRequest $request)
    {
        $evento = Evento::buscar($request->id);
        if ($evento->EVENTO_ATIVO) {
            $evento->EVENTO_ATIVO = 0;
        } else {
            $evento->EVENTO_ATIVO = 1;
        }

        return response($evento, 200);
    }
}
