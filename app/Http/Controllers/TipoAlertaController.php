<?php

namespace App\Http\Controllers;

use App\Http\Requests\TipoAlerta\TipoAlertaCreateRequest;
use App\Http\Requests\TipoAlerta\TipoAlertaUpdateRequest;
use App\Models\TipoAlerta;
use Illuminate\Http\Request;

class TipoAlertaController extends Controller
{
    private $label = 'Tipo de Alerta';

    public function view()
    {
        return view('tipo_alerta.tipo_alerta_view');
    }

    public function inserir(TipoAlertaCreateRequest $request)
    {
        $tipoAlerta = new TipoAlerta($request->input());
        $tipoAlerta->save();

        return response([
            "cod" => 1,
            "msg" => "$this->label adicionado com sucesso",
            "retorno" => $tipoAlerta
        ], 200);
    }

    public function listar(Request $request)
    {
        $tipoAlerta = TipoAlerta::listar($request)->paginate();

        return response([
            'retorno' => $tipoAlerta,
            'msg' => "$this->label listado com sucesso",
        ], 200);
    }

    public function buscar(Request $request)
    {
        $tipoAlerta = TipoAlerta::buscar($request->id);

        return response([
            'retorno' => $tipoAlerta,
            'msg' => "$this->label buscado com sucesso",
        ], 200);
    }

    public function alterar(TipoAlertaUpdateRequest $request)
    {
        $tipoAlerta = TipoAlerta::buscar($request->TIPO_ALERTA_ID);
        $tipoAlerta->fill($request->input());
        $tipoAlerta->update();

        return response([
            'retorno' => $tipoAlerta,
            'msg' => "$this->label alterado com sucesso",
        ], 200);
    }
}
