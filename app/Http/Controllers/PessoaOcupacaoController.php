<?php

namespace App\Http\Controllers;

use App\Http\Requests\PessoaOcupacao\PessoaOcupacaoCreateRequest;
use App\Http\Requests\PessoaOcupacao\PessoaOcupacaoDeleteRequest;
use App\Http\Requests\PessoaOcupacao\PessoaOcupacaoUpdateRequest;
use App\Models\Pessoa;
use App\Models\PessoaOcupacao;
use Exception;
use Illuminate\Support\Facades\DB;

class PessoaOcupacaoController extends Controller
{
    public function create(PessoaOcupacaoCreateRequest $request)
    {
        try {
            DB::beginTransaction();
            $pessoaId = $request->post("PESSOA_ID");
            $pessoaOcupacao = new PessoaOcupacao($request->post());
            $pessoaOcupacao->save();
            Pessoa::atualizarStatus($pessoaOcupacao->PESSOA_ID);
            DB::commit();
            return response(Pessoa::buscar($pessoaId));
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function update(PessoaOcupacaoUpdateRequest $request)
    {
        try {
            DB::beginTransaction();
            $pessoaId = $request->post("PESSOA_ID");
            $pessoaOcupacao = PessoaOcupacao::find($request->post("PESSOA_OCUPACAO_ID"));
            $pessoaOcupacao->fill($request->post());
            $pessoaOcupacao->update();
            Pessoa::atualizarStatus($pessoaOcupacao->PESSOA_ID);
            DB::commit();
            return response(Pessoa::buscar($pessoaId));
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function delete(PessoaOcupacaoDeleteRequest $request)
    {
        $pessoaId = $request->post("PESSOA_ID");
        try {
            DB::beginTransaction();
            PessoaOcupacao::find($request->post("PESSOA_OCUPACAO_ID"))->delete();
            DB::commit();
            return response(Pessoa::buscar($pessoaId));
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }
}
