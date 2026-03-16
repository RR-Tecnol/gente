<?php

namespace App\Http\Controllers;

use App\Http\Requests\Perfil\PerfilCreateRequest;
use App\Http\Requests\Perfil\PerfilUpdateRequest;
use App\Models\Acesso;
use App\Models\Aplicacao;
use App\Models\Perfil;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PerfilController extends Controller
{
    public function view()
    {
        return view("perfil.perfil_view")
            ->with([
                "aplicacoes" => Aplicacao::listAll()
            ]);
    }

    public function create(PerfilCreateRequest $request)
    {
        try {
            DB::beginTransaction();
            $perfil = new Perfil($request->post());
            $perfil->save();
            $acessosJson = $request->post("acessos");
            if ($acessosJson) {
                foreach ($acessosJson as $acessoJson) {
                    $acesso = new Acesso($acessoJson);
                    $acesso->PERFIL_ID = $perfil->PERFIL_ID;
                    $acesso->save();
                }
            }
            DB::commit();
            return response(Perfil::getById($perfil->PERFIL_ID));
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function update(PerfilUpdateRequest $request)
    {
        try {
            DB::beginTransaction();
            $perfil = Perfil::find($request->input("PERFIL_ID"));
            $perfil->fill($request->input());
            $perfil->update();
            Acesso::deleteByPerfilId($perfil->PERFIL_ID);
            $acessosJson = $request->input("acessos");
            if ($acessosJson) {
                foreach ($acessosJson as $acessoJson) {
                    $acesso = new Acesso($acessoJson);
                    $acesso->PERFIL_ID = $perfil->PERFIL_ID;
                    $acesso->save();
                }
            }
            DB::commit();
            return response(Perfil::getById($perfil->PERFIL_ID));
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function search(Request $request)
    {
        $perfil = Perfil::search($request->input("valorPesquisa"))
            ->when($request->orderBy, function (Builder $query) use ($request) {
                $request->sort = $request->sort ?: 'asc';
                $query->orderBy($request->orderBy, $request->sort);
            })
            ->when(!$request->orderBy, function (Builder $query) {
                $query->orderBy('PERFIL_ID');
            })
            ->paginate();
        return response($perfil);
    }

    public function list()
    {
        return response(Perfil::listAll());
    }
}
