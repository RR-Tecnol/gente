<?php

namespace App\Http\Controllers;

use App\Http\Requests\TipoDocumento\TipoDocumentoCreateRequest;
use App\Http\Requests\TipoDocumento\TipoDocumentoUpdateRequest;
use App\Models\TipoDocumento;
use Illuminate\Http\Request;

class TipoDocumentoController extends Controller
{
    public function view()
    {
        return view('tipo_documento.tipo_documento_view');
    }

    public function inserir(TipoDocumentoCreateRequest $request)
    {
        $tipoDocumento = new TipoDocumento($request->input());
        $tipoDocumento->TIPO_DOCUMENTO_ATIVO = 1;
        $tipoDocumento->save();

        return response([
            "cod" => 1,
            "msg" => "Tipo de documento adicionado com sucesso",
            "retorno" => $tipoDocumento
        ], 200);
    }

    public function listar(Request $request)
    {
        $tipoDocumento = TipoDocumento::listar($request)->paginate();

        return response([
            "cod" => 1,
            "msg" => "Tipo de documento listado com sucesso",
            "retorno" => $tipoDocumento
        ], 200);
    }

    public function pesquisar(Request $request)
    {
        $tipoDocumento = TipoDocumento::pesquisar($request);

        return response([
            "cod" => 1,
            "msg" => "Tipo de documento pesquisado com sucesso",
            "retorno" => $tipoDocumento
        ], 200);
    }

    public function buscar(Request $request)
    {
        $tipoDocumento = TipoDocumento::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Tipo de documento id {$request->id} buscado com sucesso",
            "retorno" => $tipoDocumento
        ], 200);
    }

    public function deletar(Request $request)
    {
        $tipoDocumento = TipoDocumento::buscar($request->id);
        $tipoDocumento->TIPO_DOCUMENTO_ATIVO = 0;
        $tipoDocumento->save();

        return response([
            "cod" => 1,
            "msg" => "Tipo de documento id {$request->id} deletado com sucesso",
            "retorno" => $tipoDocumento
        ], 200);
    }

    public function alterar(TipoDocumentoUpdateRequest $request)
    {
        $tipoDocumento = TipoDocumento::buscar($request->TIPO_DOCUMENTO_ID);
        $tipoDocumento->fill($request->post());
        $tipoDocumento->update();

        return response([
            "cod" => 1,
            "msg" => "Tipo de documento id {$request->TIPO_DOCUMENTO_ID} alterado com sucesso",
            "retorno" => $tipoDocumento
        ], 200);
    }
}
