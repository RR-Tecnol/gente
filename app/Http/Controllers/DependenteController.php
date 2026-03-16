<?php

namespace App\Http\Controllers;

use App\Http\Requests\Depedente\DependenteDeleteRequest;
use App\Http\Requests\Dependente\DependenteCreateRequest;
use App\Http\Requests\Dependente\DependenteUpdateRequest;
use App\Models\Dependente;
use App\Models\Documento;
use App\Models\Pessoa;
use App\MyLibs\TipoDocumentoEnum;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DependenteController extends Controller
{
    private $label = 'Dependente';

    public function create(DependenteCreateRequest $request)
    {
        try {
            DB::beginTransaction();
            $dependente = new Dependente($request->post());
            $dependente->save();
            DB::commit();
            $pessoaId = $request->post("PESSOA_ID");
            return response(Pessoa::buscar($pessoaId));
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function update(DependenteUpdateRequest $request)
    {
        try {
            DB::beginTransaction();
            $dependente = Dependente::find($request->input("DEPENDENTE_ID"));
            $dependente->fill($request->input());
            $dependente->update();
            DB::commit();
            return response(Pessoa::buscar($request->input("PESSOA_ID")));
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function listar(Request $request)
    {
        $dependente = Dependente::listar($request)->paginate();

        return response([
            'retorno' => $dependente,
            'msg' => "$this->label listado com sucesso",
        ], 200);
    }

    public function buscar($id)
    {
        $dependente = Dependente::buscar($id);

        return response([
            'retorno' => $dependente,
            'msg' => "$this->label buscado com sucesso",
        ], 200);
    }

    public function alterar(DependenteUpdateRequest $request)
    {
        $pessoaId = $request->post("PESSOA_ID");
        $dependente = Dependente::find($request->DEPENDENTE_ID);
        $dependente->fill($request->input());
        $dependente->update();

        return response(Pessoa::buscar($pessoaId));
    }

    public function delete(DependenteDeleteRequest $request)
    {
        try {
            DB::beginTransaction();
            Dependente::find($request->input("DEPENDENTE_ID"))->delete();
            DB::commit();
            return response(Pessoa::buscar($request->input("PESSOA_ID")));
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }
}
