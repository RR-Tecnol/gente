<?php

namespace App\Http\Controllers;

use App\Http\Requests\Folha\FolhaCreateRequest;
use App\Http\Requests\Folha\FolhaDeleteRequest;
use App\Http\Requests\Folha\FolhaUpdateRequest;
use App\Jobs\ProcessarFolhaJob;
use App\Models\DetalheFolha;
use App\Models\Folha;
use App\Models\HistoricoFolha;
use App\Models\Setor;
use App\Models\TabelaGenerica;
use App\Models\Unidade;
use App\Models\Vinculo;
use App\MyLibs\RTG;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FolhaController extends Controller
{
    public function view()
    {
        $vinculos = Vinculo::listAll();
        $tiposFolhas = TabelaGenerica::listarColunasTabela(RTG::TIPOS_FOLHA);
        $setores = Setor::listAll();
        $unidades = Unidade::listAll();

        return view('folha.folha_view', compact('vinculos', 'tiposFolhas', 'setores', 'unidades'));
    }

    public function calculoView()
    {
        return view('calculo.calculo_view');
    }

    public function contraChequeView()
    {
        $funcionarios = Auth::user()->funcionario
            ? Auth::user()->funcionario->pessoa->funcionarios
            : collect();
        return view('calculo.contra_cheque_view', compact('funcionarios'));
    }

    public function inserir(FolhaCreateRequest $request)
    {
        ProcessarFolhaJob::dispatch($request->input(), auth()->id())->afterCommit();
        return response("Processando...");
    }

    public function listar(Request $request)
    {
        $folha = Folha::listar($request)->paginate();

        return response($folha, 200);
    }

    public function pesquisar(Request $request)
    {
        $folhas = Folha::listar($request)
            ->whereHas('historicoUltimo', function (Builder $query) {
                $query->where('HISTORICO_FOLHA_STATUS', 4);
            })
            ->select('FOLHA_COMPETENCIA')
            ->distinct()
            ->orderBy('FOLHA_COMPETENCIA', 'desc')
            ->get();

        $saida = [];

        foreach ($folhas as $key => $folha) {
            array_push($saida, [
                "id" => $key,
                "label" => $folha->FOLHA_COMPETENCIA,
                "children" => $this->detalhe($request->FUNCIONARIO_ID, $folha->FOLHA_COMPETENCIA)
            ]);
        }

        return response($saida, 200);
    }

    public function detalhe($funcionarioId, $folhaCompetencia)
    {

        $periodo = explode('/', $folhaCompetencia);
        $competencia = "$periodo[1]$periodo[0]";

        $detalhes = DetalheFolha::with([
            'EventosDetalhesFolhas.evento.incidencia',
            'EventosDetalhesFolhas.evento.historicoEvento.formaCalculo',
            'folha.tipoFolha'
        ])->where('FUNCIONARIO_ID', $funcionarioId)
            ->whereHas('folha', function (Builder $query) use ($competencia) {
                return $query->where('FOLHA_COMPETENCIA', $competencia)
                    ->whereHas('historicoUltimo', function ($query) {
                        $query->where('HISTORICO_FOLHA_STATUS', 4);
                    });
            })->get();

        foreach ($detalhes as $key => $detalhe) {
            $detalhe->id = $detalhe->DETALHE_FOLHA_ID;
            $detalhe->label = "Folha " . $detalhe->folha->vinculo->VINCULO_SIGLA . " " . $competencia;
        }

        return $detalhes;
    }

    public function buscar(Request $request)
    {
        $folha = Folha::buscar($request->id);

        return response($folha, 200);
    }

    public function alterar(FolhaUpdateRequest $request)
    {
        // Fase 3: aposentado Folha::reprocessarFolha() (T-SQL sp_gera_folha SQL Server-only).
        // Reaproveitamos ProcessarFolhaJob (assíncrono) que internamente chama MotorFolhaService::calcularFolha().
        // Isso garante:
        //   - Mesmo motor canônico em ambas as rotas (insert e update)
        //   - Idempotência R7-R10 (lançamentos contábeis re-gerados sem duplicar)
        //   - Audit trail F4 da jornada financeira informal (GAP-MF-05) também é registrado em re-processo
        //   - Compatibilidade SQL Server / SQLite cross-driver
        DB::beginTransaction();
        $folha = Folha::buscar($request->FOLHA_ID);
        $folha->fill($request->post());
        $folha->save(); // persistir alterações de cabeçalho ANTES de re-processar
        DB::commit();

        // Despachar reprocessamento assíncrono — payload deve conter FOLHA_ID
        // para que o Job carregue a folha corretamente.
        ProcessarFolhaJob::dispatch(
            ['FOLHA_ID' => $folha->FOLHA_ID] + $request->post(),
            auth()->id()
        )->afterCommit();

        return response($folha, 200);
    }

    public function deletar(FolhaDeleteRequest $request)
    {
        $folha = Folha::buscar($request->FOLHA_ID);
        HistoricoFolha::setHistorico($folha, 4);

        return response($folha, 200);
    }
}
