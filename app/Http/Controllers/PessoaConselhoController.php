<?php

namespace App\Http\Controllers;

use App\Http\Requests\PessoaConselho\PessoaConselhoUpdateRequest;
use App\Http\Requests\PessoaConselhoCreateRequest;
use App\Http\Requests\PessoaConselhoDeleteRequest;
use App\Models\Pessoa;
use App\Models\PessoaConselho;
use Exception;
use Illuminate\Support\Facades\DB;

class PessoaConselhoController extends Controller
{
    public function create(PessoaConselhoCreateRequest $request)
    {
        try {
            DB::beginTransaction();
            $pessoaId = $request->post("PESSOA_ID");
            $pc = new PessoaConselho($request->post());
            $pc->save();
            Pessoa::atualizarStatus($pc->PESSOA_ID);
            DB::commit();
            return response(Pessoa::buscar($pessoaId));
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function update(PessoaConselhoUpdateRequest $request)
    {
        try {
            DB::beginTransaction();
            $pessoaId = $request->post("PESSOA_ID");
            $pc = PessoaConselho::find($request->post("PESSOA_CONSELHO_ID"));
            $pc->fill($request->post());
            $pc->update();
            Pessoa::atualizarStatus($pc->PESSOA_ID);
            DB::commit();
            return response(Pessoa::buscar($pessoaId));
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function delete(PessoaConselhoDeleteRequest $request)
    {
        $pessoaId = $request->post("PESSOA_ID");
        $pc = PessoaConselho::find($request->post("PESSOA_CONSELHO_ID"));
        try {
            $pc->delete();
            return response(Pessoa::buscar($pessoaId));
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
