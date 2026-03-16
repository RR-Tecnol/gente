<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documento\DocumentoCreateRequest;
use App\Http\Requests\Documento\DocumentoDeleteRequest;
use App\Http\Requests\Documento\DocumentoUpdateRequest;
use App\Models\Documento;
use App\Models\Pessoa;
use App\Models\TipoDocumento;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentoController extends Controller
{
    public function create(DocumentoCreateRequest $request)
    {
        try {
            DB::beginTransaction();
            $documento = new Documento($request->post());
            $documento->save();
            Pessoa::atualizarStatus($documento->PESSOA_ID);
            DB::commit();
            return response(Pessoa::buscar($documento->PESSOA_ID));
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function update(DocumentoUpdateRequest $request)
    {
        try {
            DB::beginTransaction();
            $documento = Documento::find($request->input("DOCUMENTO_ID"));
            $documento->fill($request->input());
            $documento->update();
            Pessoa::atualizarStatus($documento->PESSOA_ID);
            DB::commit();
            return response(Pessoa::buscar($documento->PESSOA_ID));
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function listar(Request $request)
    {
        $documentos = Documento::listar($request);

        $documento = [];
        $tipo_documento = [];
        foreach ($documentos as $dado) {
            $tipo_documento[] = $dado->tipo_documento;
            $documento[] = $dado;
        }

        $obrigatorio = [];
        foreach (TipoDocumento::obrigatorio() as $tipo) {
            if (!in_array($tipo, $tipo_documento) && ($tipo->TIPO_DOCUMENTO_ATIVO == 1)) {
                $obrigatorio[] = "<li><b>$tipo->TIPO_DOCUMENTO_DESCRICAO</b> é um documento obrigatório</li>";
            }
        }

        return response([
            "cod" => 1,
            "msg" => "Documento listado com sucesso",
            "retorno" => $documento,
            "obrigatorio" => $obrigatorio
        ], 200);
    }

    public function buscar(Request $request)
    {
        $documento = Documento::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Documento id {$request->id} buscado com sucesso",
            "retorno" => $documento
        ], 200);
    }

    public function delete(DocumentoDeleteRequest $request)
    {
        $pessoaId = $request->post("PESSOA_ID");
        $documento = Documento::find($request->post('DOCUMENTO_ID'));
        try {
            $documento->delete();
            return response(Pessoa::buscar($pessoaId));
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function alterar(DocumentoUpdateRequest $request)
    {
        $documento = Documento::buscar($request->DOCUMENTO_ID);
        $documento->fill($request->post());
        $documento->update();

        return response([
            "cod" => 1,
            "msg" => "Documento id {$request->DOCUMENTO_ID} alterado com sucesso",
            "retorno" => $documento
        ], 200);
    }
}
