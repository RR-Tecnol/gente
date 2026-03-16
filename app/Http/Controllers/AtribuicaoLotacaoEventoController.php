<?php

namespace App\Http\Controllers;

use App\Http\Requests\AtribuicaoLotacaoEvento\AtribuicaoLotacaoEventoCreateRequest;
use App\Models\Atribuicao;
use App\Models\AtribuicaoLotacaoEvento;
use App\Models\Evento;
use App\Models\Setor;
use App\Models\Vinculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AtribuicaoLotacaoEventoController extends Controller
{
    public function view()
    {
        $vinculos = Vinculo::where('VINCULO_ATIVO', 1)->orderBy('VINCULO_DESCRICAO')->get();
        $setores = Setor::with(['unidade'])->where('SETOR_ATIVO', 1)->orderBy('SETOR_NOME')->get();
        $atribuicoes = Atribuicao::where('ATRIBUICAO_ATIVA', 1)->orderBy('ATRIBUICAO_NOME')->get();
        $eventos = Evento::listar()->where('EVENTO_ATIVO', 1)
            ->where('EVENTO_IMPOSTO', 0)
            ->where('EVENTO_SISTEMA', 0)
            ->whereHas('historicoEvento')
            ->orderBy('EVENTO_DESCRICAO')
            ->get();
        return view('atribuicao_lotacao_evento.atribuicao_lotacao_evento_view', compact("vinculos", "setores", "atribuicoes", "eventos"));
    }

    public function inserir(AtribuicaoLotacaoEventoCreateRequest $request)
    {
        $mensagemErro = [];
        $mensagemSucesso = [];

        DB::beginTransaction();
        foreach ($request->lotacoes as $lotacao) {
            foreach ($request->eventos as $evento) {
                $atribuicaoLotacaoEvento = new AtribuicaoLotacaoEvento($evento);
                $atribuicaoLotacaoEvento->ATRIBUICAO_LOTACAO_ID = $lotacao["ATRIBUICAO_LOTACAO_ID"];
                $atribuicaoLotacaoEvento->EVENTO_ID = $evento["EVENTO_ID"];
                $atribuicaoLotacaoEvento->ATRIBUICAO_LOTACAO_EVENTO_DATA_CADASTRO = date("Y-m-d H:i:s");
                $atribuicaoLotacaoEvento->ATRIBUICAO_LOTACAO_EVENTO_EXCLUIDO = 0;
                $atribuicaoLotacaoEvento->USUARIO_ID = Auth::id();
                $validacao = $this->validarVingencia($atribuicaoLotacaoEvento);
                $nomeFuncionario = Str::title($lotacao['lotacao']['funcionario']['pessoa']['PESSOA_NOME']);
                $nomeEvento = Str::title($evento['evento']['EVENTO_DESCRICAO']);

                if ($validacao) {
                    $msg = "Sucesso: Evento <strong>$nomeEvento</strong> cadastrado com sucesso para o Funcionário <strong>$nomeFuncionario</strong>.";
                    array_push($mensagemSucesso, $msg);
                    $atribuicaoLotacaoEvento->save();
                } else {
                    $msg = "Atenção: Já existe o evento <strong>$nomeEvento</strong> para a lotação do Funcionário <strong>$nomeFuncionario</strong> com a mesma vingência.";
                    array_push($mensagemErro, $msg);
                }
            }
        }
        DB::commit();

        return response([
            "msgErro" => $mensagemErro,
            "msgSucesso" => $mensagemSucesso
        ]);
    }

    public function listar(Request $request)
    {
        $atribuicaoLotacaoEvento = AtribuicaoLotacaoEvento::listar($request)->paginate();

        return response($atribuicaoLotacaoEvento, 200);
    }

    public function buscar(Request $request)
    {
        $atribuicaoLotacaoEvento = AtribuicaoLotacaoEvento::buscar($request->id);

        return response($atribuicaoLotacaoEvento, 200);
    }


    public function validarVingencia($request)
    {
        if (($request['ATRIBUICAO_LOTACAO_EVENTO_INICIO']) == null)
            return false;

        $periodo = explode('/', $request['ATRIBUICAO_LOTACAO_EVENTO_INICIO']);
        $inicio = "$periodo[1]$periodo[0]";

        $atribuicaoLotacaoEvento = AtribuicaoLotacaoEvento::where('EVENTO_ID', $request['EVENTO_ID'])
            ->where('ATRIBUICAO_LOTACAO_ID', $request['ATRIBUICAO_LOTACAO_ID'])
            ->whereNull('ATRIBUICAO_LOTACAO_EVENTO_FIM')
            ->first();
        if (!$atribuicaoLotacaoEvento) {
            $atribuicaoLotacaoEvento = AtribuicaoLotacaoEvento::where('EVENTO_ID', $request['EVENTO_ID'])
                ->where('ATRIBUICAO_LOTACAO_ID', $request['ATRIBUICAO_LOTACAO_ID'])
                ->where('ATRIBUICAO_LOTACAO_EVENTO_FIM', '>=', $inicio)
                ->first();
        }
        return $atribuicaoLotacaoEvento ? false : true;
    }
}
