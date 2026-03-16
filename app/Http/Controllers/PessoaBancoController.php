<?php

namespace App\Http\Controllers;

use App\Http\Requests\PessoaBanco\PessoaBancoCreateRequest;
use App\Http\Requests\PessoaBanco\PessoaBancoDeleteRequest;
use App\Http\Requests\PessoaBanco\PessoaBancoUpdateRequest;
use App\Models\Pessoa;
use App\Models\PessoaBanco;
use Exception;
use Illuminate\Support\Facades\DB;

class PessoaBancoController extends Controller
{
    public function create(PessoaBancoCreateRequest $request)
    {
        try {
            DB::beginTransaction();
            $pessoaId = $request->post("PESSOA_ID");
            $pessoaBanco = new PessoaBanco($request->post());
            $pessoaBanco->save();
            Pessoa::atualizarStatus($pessoaBanco->PESSOA_ID);
            DB::commit();
            return response(Pessoa::buscar($pessoaId));
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function update(PessoaBancoUpdateRequest $request)
    {
        try {
            DB::beginTransaction();
            $pessoaId = $request->post("PESSOA_ID");
            $pessoaBanco = PessoaBanco::find($request->post('PESSOA_BANCO_ID'));
            $pessoaBanco->fill($request->post());
            $pessoaBanco->update();
            Pessoa::atualizarStatus($pessoaBanco->PESSOA_ID);
            DB::commit();
            return response(Pessoa::buscar($pessoaId));
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function delete(PessoaBancoDeleteRequest $request)
    {
        $pessoaId = $request->post("PESSOA_ID");
        try {
            PessoaBanco::find($request->post("PESSOA_BANCO_ID"))->delete();
            return response(Pessoa::buscar($pessoaId));
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
