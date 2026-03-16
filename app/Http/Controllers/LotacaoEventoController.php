<?php

namespace App\Http\Controllers;

use App\Http\Requests\LotacaoEvento\LotacaoEventoCreateRequest;
use App\Http\Requests\LotacaoEvento\LotacaoEventoDeleteRequest;
use App\Http\Requests\LotacaoEvento\LotacaoEventoUpdateRequest;
use App\Models\Atribuicao;
use App\Models\Evento;
use App\Models\LotacaoEvento;
use App\Models\Setor;
use App\Models\Vinculo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LotacaoEventoController extends Controller
{
    public function view()
    {
        $vinculos = Vinculo::where('VINCULO_ATIVO', 1)->orderBy('VINCULO_DESCRICAO')->get();
        $setores = Setor::where('SETOR_ATIVO', 1)->orderBy('SETOR_NOME')->get();
        $atribuicoes = Atribuicao::where('ATRIBUICAO_ATIVA', 1)->orderBy('ATRIBUICAO_NOME')->get();
        $eventos = Evento::listar()->where('EVENTO_ATIVO', 1)
            ->where('EVENTO_IMPOSTO', 0)
            ->where('EVENTO_SISTEMA', 0)
            ->whereHas('historicoEvento')
            ->orderBy('EVENTO_DESCRICAO')
            ->get();
        return view('lotacao_evento.lotacao_evento_view', compact("vinculos", "setores", "atribuicoes", "eventos"));
    }

    public function inserir(LotacaoEventoCreateRequest $request)
    {
        $mensagemErro = [];
        $mensagemSucesso = [];

        DB::beginTransaction();
        foreach ($request->lotacoes as $lotacao) {
            foreach ($request->eventos as $evento) {
                $lotacaoEvento = new LotacaoEvento($evento);
                $lotacaoEvento->LOTACAO_ID = $lotacao["LOTACAO_ID"];
                $lotacaoEvento->EVENTO_ID = $evento["EVENTO_ID"];
                $lotacaoEvento->LOTACAO_EVENTO_DATA_CADASTRO = date("Y-m-d H:i:s");
                $lotacaoEvento->LOTACAO_EVENTO_EXCLUIDO = 0;
                $lotacaoEvento->USUARIO_ID = Auth::id();
                $validacao = $this->validarVingencia($lotacaoEvento);
                $nomeFuncionario = Str::title($lotacao['funcionario']['pessoa']['PESSOA_NOME']);
                $nomeEvento = Str::title($evento['evento']['EVENTO_DESCRICAO']);

                if ($validacao) {
                    $msg = "Sucesso: Evento <strong>$nomeEvento</strong> cadastrado com sucesso para o Funcionário <strong>$nomeFuncionario</strong>.";
                    array_push($mensagemSucesso, $msg);
                    $lotacaoEvento->save();
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
        $lotacaoEvento = LotacaoEvento::listar($request)->paginate();

        return response($lotacaoEvento, 200);
    }

    public function buscar(Request $request)
    {
        $lotacaoEvento = LotacaoEvento::buscar($request->id);

        return response($lotacaoEvento, 200);
    }

    public function validarVingencia($request)
    {
        if (($request['LOTACAO_EVENTO_INICIO']) == null)
            return false;

        $periodo = explode('/', $request['LOTACAO_EVENTO_INICIO']);
        $inicio = "$periodo[1]$periodo[0]";

        $lotacaoEvento = LotacaoEvento::where('EVENTO_ID', $request['EVENTO_ID'])
            ->where('LOTACAO_ID', $request['LOTACAO_ID'])
            ->whereNull('LOTACAO_EVENTO_FIM')
            ->first();
        if (!$lotacaoEvento) {
            $lotacaoEvento = LotacaoEvento::where('EVENTO_ID', $request['EVENTO_ID'])
                ->where('LOTACAO_ID', $request['LOTACAO_ID'])
                ->where('LOTACAO_EVENTO_FIM', '>=', $inicio)
                ->first();
        }

        return $lotacaoEvento ? false : true;
    }
}
