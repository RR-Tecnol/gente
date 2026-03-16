<?php

namespace App\Http\Controllers;

use App\Http\Requests\TabelaGenerica\TabelaGenericaAlterarColunaRequest;
use App\Http\Requests\TabelaGenerica\TabelaGenericaCreateRequest;
use App\Http\Requests\TabelaGenerica\TabelaGenericaInserirColunaRequest;
use App\Http\Requests\TabelaGenerica\TabelaGenericaInserirTabelaRequest;
use App\Http\Requests\TabelaGenerica\TabelaGenericaRemoverColunaRequest;
use App\Http\Requests\TabelaGenerica\TabelaGenericaUpdateRequest;
use App\Models\TabelaGenerica;
use Exception;
use Illuminate\Http\Request;

class TabelaGenericaController extends Controller
{
    public function view()
    {
        return view('tabela_generica.tabela_generica_view');
    }

    public function inserir(TabelaGenericaCreateRequest $request)
    {
        $tabelas = new TabelaGenerica($request->input());
        $tabelas->save();

        return response([
            "cod" => 1,
            "msg" => "TabelaGenerica adicionado com sucesso",
            "retorno" => $tabelas
        ], 200);
    }

    public function listar()
    {
        $tabelas = TabelaGenerica::listarTabelas();

        return response([
            "cod" => 1,
            "msg" => "TabelaGenerica listado com sucesso",
            "retorno" => $tabelas
        ]);
    }

    public function pesquisar(Request $request)
    {
        $tabelas = TabelaGenerica::pesquisar($request);

        return response([
            "cod" => 1,
            "msg" => "TabelaGenerica pesquisado com sucesso",
            "retorno" => $tabelas
        ], 200);
    }

    public function buscar(Request $request)
    {
        $tabelas = TabelaGenerica::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "TabelaGenerica id {$request->id} buscado com sucesso",
            "retorno" => $tabelas
        ], 200);
    }

    public function deletar(Request $request)
    {
        $tabelas = TabelaGenerica::buscar($request->id);
        if ($tabelas->ATIVO == 1) {
            $tabelas->ATIVO = 0;
            $msg = "Tabela Genérica id {$request->id} desativado com sucesso";
        } else {
            $tabelas->ATIVO = 1;
            $msg = "Tabela Genérica id {$request->id} ativado com sucesso";
        }
        $tabelas->save();

        return response([
            "cod" => 1,
            "msg" => $msg,
            "retorno" => $tabelas
        ], 200);
    }

    public function alterar(TabelaGenericaUpdateRequest $request)
    {
        $tabelas = TabelaGenerica::buscar($request->TABELA_GENERICA_ID);
        $tabelas->fill($request->post());
        $tabelas->update();

        return response([
            "cod" => 1,
            "msg" => "TabelaGenerica id {$request->TABELA_GENERICA_ID} alterado com sucesso",
            "retorno" => $tabelas
        ], 200);
    }

    public function alterarColuna(TabelaGenericaAlterarColunaRequest $request)
    {
        $tabelaGenerica = TabelaGenerica::find($request->post("TABELA_GENERICA_ID"));
        $tabelaGenerica->fill($request->post());
        $tabelaGenerica->update();
        return response(TabelaGenerica::listarColunasTabela($tabelaGenerica->TABELA_ID, 0, ['tabela']));
    }

    public function inserirColuna(TabelaGenericaInserirColunaRequest $request)
    {
        $colunaId = TabelaGenerica::obterUltimoIdDeColuna($request->post("TABELA_ID"));
        $colunaId++;
        $tabelaGenerica = new TabelaGenerica($request->post());
        $tabelaGenerica->COLUNA_ID = $colunaId;
        $tabelaGenerica->save();
        return response(TabelaGenerica::listarColunasTabela($tabelaGenerica->TABELA_ID, 0, ['tabela']));
    }

    public function removerColuna(TabelaGenericaRemoverColunaRequest $request)
    {
        try {
            TabelaGenerica::find($request->post("TABELA_GENERICA_ID"))->delete();
            return response(TabelaGenerica::listarColunasTabela($request->post("TABELA_ID"), 0, ['tabela']));
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function inserirTabela(TabelaGenericaInserirTabelaRequest $request)
    {
        $tabelaId = TabelaGenerica::obterUltimoIdDeTabela();
        $tabelaId++;
        $tabela = new TabelaGenerica($request->post());
        $tabela->TABELA_ID = $tabelaId;
        $tabela->COLUNA_ID = 0;
        $tabela->ATIVO = 1;
        $tabela->save();
        return response(TabelaGenerica::listarTabelas());
    }

    public function listarColunas(Request $request)
    {
        $tabelaId = $request->query("tabelaId");
        $ativos = $request->query("ativos");
        return response(TabelaGenerica::listarColunasTabela($tabelaId, $ativos, ['tabela']));
    }

    public function carregar()
    {
        $tabelas = TabelaGenerica::listarColunasTabela(1);

        return response([
            "cod" => 1,
            "msg" => "TabelaGenerica carregada com sucesso",
            "retorno" => $tabelas
        ], 200);
    }
}
