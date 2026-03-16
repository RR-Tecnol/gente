<?php

namespace App\Http\Controllers;

use App\Http\Requests\DetalheEscalaItem\DetalheEscalaItemCreateRequest;
use App\Http\Requests\DetalheEscalaItem\DetalheEscalaItemDelete;
use App\Http\Requests\DetalheEscalaItem\EscalaDetalheItemUpdateRequest;
use App\Http\Requests\DetalheEscalaItem\MacroCreateRequest;
use App\Models\DetalheEscala;
use App\Models\DetalheEscalaAlerta;
use App\Models\DetalheEscalaItem;
use App\Models\Escala;
use App\Models\HistoricoEscala;
use App\MyLibs\StatusEscalaEnum;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DetalheEscalaItemController extends Controller
{
    public function listar()
    {
        $detalheEscalaItem = DetalheEscalaItem::listar();

        return response([
            "cod" => 1,
            "msg" => "Detalhe Escala Item listado com sucesso",
            "retorno" => $detalheEscalaItem
        ], 200);
    }

    public function pesquisar(Request $request)
    {
        $detalheEscala = DetalheEscalaItem::pesquisar($request);

        return response($detalheEscala, 200);
    }

    public function inserir(DetalheEscalaItemCreateRequest $request)
    {
        DB::beginTransaction();
        $detalheEscalaItem = new DetalheEscalaItem($request->input());
        $detalheEscalaItem->DETALHE_ESCALA_ITEM_FALTA = 0;
        $detalheEscalaItem->DETALHE_ESCALA_ITEM_ATRASO = 0;
        $detalheEscalaItem->save();

        $alerta = new DetalheEscalaAlerta();
        $alerta->gerarAlerta($detalheEscalaItem->detalheEscala);
        HistoricoEscala::setHistoricoEscala($detalheEscalaItem->detalheEscala->escala, StatusEscalaEnum::ATUALIZADA);
        DB::commit();

        return response([
            'escala' => Escala::buscar($detalheEscalaItem->detalheEscala->escala->ESCALA_ID),
            'itens' => DetalheEscala::buscar($detalheEscalaItem->DETALHE_ESCALA_ID)->detalheEscalaItens,
        ], 200);
    }

    public function deletar(DetalheEscalaItemDelete $request)
    {
        DB::beginTransaction();
        $detalheEscalaItem = DetalheEscalaItem::buscar($request->DETALHE_ESCALA_ITEM_ID);

        $alerta = new DetalheEscalaAlerta();
        $alerta->gerarAlerta($detalheEscalaItem->detalheEscala);
        HistoricoEscala::setHistoricoEscala($detalheEscalaItem->detalheEscala->escala, StatusEscalaEnum::ATUALIZADA);
        $detalheEscala = DetalheEscala::buscar($detalheEscalaItem->DETALHE_ESCALA_ID);
        $detalheEscalaItem->delete();

        DB::commit();

        return response([
            "cod" => 1,
            "msg" => "Detalhe Escala Item removido com sucesso",
            "retorno" => Escala::buscar($detalheEscala->ESCALA_ID)
        ], 200);
    }

    public function alterar(EscalaDetalheItemUpdateRequest $request)
    {
        $detalheEscala = DetalheEscalaItem::buscar($request->DETALHE_ESCALA_ITEM_ID);
        $detalheEscala->fill($request->post());
        $detalheEscala->update();

        return response([
            "cod" => 1,
            "msg" => "Detalhe Escala Item id {$request->DETALHE_ESCALA_ITEM_ID} alterado com sucesso",
            "retorno" => Escala::buscar($detalheEscala->detalheEscala->ESCALA_ID)
        ], 200);
    }

    public function salvarItens(Request $request)
    {
        $dados = $request->input();

        $detalheEscalaId = $dados['DETALHE_ESCALA_ID'];
        $turnoId = $dados['TURNO_ID'];
        $diasUteis = $dados['DIAS_UTEIS'] ?? [];

        DB::beginTransaction();

        foreach ($diasUteis as $data) {
            $detalheEscalaItem = new DetalheEscalaItem();
            $detalheEscalaItem->DETALHE_ESCALA_ID = $detalheEscalaId;
            $detalheEscalaItem->TURNO_ID = $turnoId;
            $detalheEscalaItem->DETALHE_ESCALA_ITEM_DATA = $data;
            $detalheEscalaItem->DETALHE_ESCALA_ITEM_FALTA = 0;
            $detalheEscalaItem->DETALHE_ESCALA_ITEM_ATRASO = 0;
            $detalheEscalaItem->save();
        }

        $detalheEscala = DetalheEscala::buscar($detalheEscalaItem->DETALHE_ESCALA_ID);

        $alerta = new DetalheEscalaAlerta();
        $alerta->gerarAlerta($detalheEscala);

        $detalheEscala->save();

        HistoricoEscala::setHistoricoEscala($detalheEscala->escala, StatusEscalaEnum::ATUALIZADA);

        DB::commit();

        return response([
            "cod" => 1,
            "msg" => "Detalhe Escala Itens criados com sucesso",
            "retorno" => Escala::buscar($detalheEscala->ESCALA_ID)
        ], 200);
    }

    public function salvarItem(Request $request)
    {
        DB::beginTransaction();
        $detalheEscalaItem = new DetalheEscalaItem($request->input());
        $detalheEscalaItem->DETALHE_ESCALA_ITEM_FALTA = 0;
        $detalheEscalaItem->DETALHE_ESCALA_ITEM_ATRASO = 0;
        $detalheEscalaItem->save();

        $detalheEscala = DetalheEscala::buscar($detalheEscalaItem->DETALHE_ESCALA_ID);

        $alerta = new DetalheEscalaAlerta();
        $alerta->gerarAlerta($detalheEscala);
        HistoricoEscala::setHistoricoEscala($detalheEscala->escala, StatusEscalaEnum::ATUALIZADA);

        DB::commit();

        return response([
            "cod" => 1,
            "msg" => "Turno do item criado com sucesso",
            "retorno" => Escala::buscar($detalheEscala->ESCALA_ID),
        ], 200);
    }

    public function alterarItem(Request $request)
    {
        $detalheEscalaItem = DetalheEscalaItem::find($request['DETALHE_ESCALA_ITEM_ID']);

        DB::beginTransaction();

        if (!$detalheEscalaItem) {
            return response([
                "cod" => 0,
                "msg" => "Item não encontrado",
            ], 404);
        }

        $detalheEscalaItem->TURNO_ID = $request['TURNO_ID'];
        $detalheEscalaItem->save();

        $detalheEscala = DetalheEscala::buscar($detalheEscalaItem->DETALHE_ESCALA_ID);

        $alerta = new DetalheEscalaAlerta();
        $alerta->gerarAlerta($detalheEscala);
        HistoricoEscala::setHistoricoEscala($detalheEscala->escala, StatusEscalaEnum::ATUALIZADA);

        DB::commit();

        return response([
            "cod" => 1,
            "msg" => "Turno do item alterado com sucesso",
            "retorno" => Escala::buscar($detalheEscala->ESCALA_ID),
        ], 200);
    }

    public function deletarItens(Request $request)
    {
        $detalheEscalaItem = $request->input();

        DB::beginTransaction();

        $itens = $request->input('detalheEscalaItens', []);

        foreach ($itens as $item) {
            if ($item['DETALHE_ESCALA_ID'] == $request->input('DETALHE_ESCALA_ID')) {
                DetalheEscalaItem::where('DETALHE_ESCALA_ITEM_ID', $item['DETALHE_ESCALA_ITEM_ID'])->delete();
            }
        }

        $detalheEscala = DetalheEscala::buscar($detalheEscalaItem['DETALHE_ESCALA_ID']);

        $alerta = new DetalheEscalaAlerta();
        $alerta->gerarAlerta($detalheEscala);
        HistoricoEscala::setHistoricoEscala($detalheEscala->escala, StatusEscalaEnum::ATUALIZADA);

        DB::commit();

        return response([
            "cod" => 1,
            "msg" => "itens removidos com sucesso.",
            "retorno" => Escala::buscar($detalheEscala->ESCALA_ID),
        ], 200);
    }

    public function salvarMacro(MacroCreateRequest $request)
    {
        $dados = $request->validated();

        $detalheEscalaId = $dados['detalhe_escala_id'];
        $tipo = (int) $dados['tipo'];
        $turnoId = $dados['turno_id'];
        $turnoSabadoId = isset($dados['turno_sabado_id']) ? $dados['turno_sabado_id'] : null;

        $detalheEscala = DetalheEscala::with('funcionario.ferias', 'funcionario.afastamentos', 'escala')
            ->findOrFail($detalheEscalaId);

        $competencia = $detalheEscala->escala->ESCALA_COMPETENCIA;
        list($mes, $ano) = explode('/', $competencia);

        $diasDoMes = $this->gerarDiasDoMes($mes, $ano);
        $diasFiltrados = $this->removerFeriasEAfastamentos($diasDoMes, $detalheEscala);

        $diasUteis = $diasFiltrados->filter(function ($d) {
            return !in_array($d->dayOfWeek, [0, 6]);
        })->values();

        $diasSabado = $diasFiltrados->filter(function ($d) {
            return $d->dayOfWeek === 6;
        })->values();

        $itensCriados = [];

        DB::beginTransaction();

        try {
            switch ($tipo) {
                case 1:
                    foreach ($diasUteis as $data) {
                        $itensCriados[] = $this->criarItemEscala($detalheEscalaId, $turnoId, $data->toDateString());
                    }
                    break;

                case 2:
                    foreach ($diasUteis as $data) {
                        $itensCriados[] = $this->criarItemEscala($detalheEscalaId, $turnoId, $data->toDateString());
                    }
                    foreach ($diasSabado as $data) {
                        $itensCriados[] = $this->criarItemEscala($detalheEscalaId, $turnoSabadoId, $data->toDateString());
                    }
                    break;

                case 3:
                    $dataSelecionada = Carbon::parse($dados['dataSelecionada']);
                    $intervalo = (int) $dados['intervalo'];
                    $fimMes = $dataSelecionada->copy()->endOfMonth();
                    $dataAtual = $dataSelecionada->copy();

                    while ($dataAtual->lte($fimMes)) {
                        $inFerias = false;
                        foreach ($detalheEscala->funcionario->ferias as $f) {
                            if ($dataAtual->gte($f->FERIAS_DATA_INICIO) && $dataAtual->lte($f->FERIAS_DATA_FIM)) {
                                $inFerias = true;
                                break;
                            }
                        }

                        $inAfast = false;
                        foreach ($detalheEscala->funcionario->afastamentos as $a) {
                            if ($dataAtual->gte($a->AFASTAMENTO_DATA_INICIO) && $dataAtual->lte($a->AFASTAMENTO_DATA_FIM)) {
                                $inAfast = true;
                                break;
                            }
                        }

                        if (!$inFerias && !$inAfast) {
                            $itensCriados[] = $this->criarItemEscala($detalheEscalaId, $turnoId, $dataAtual->toDateString());
                        }

                        $dataAtual->addDays($intervalo + 1);
                    }
                    break;

                default:
                    throw new \Exception("Tipo de macro inválido.");
            }

            $alerta = new DetalheEscalaAlerta();
            $alerta->gerarAlerta($detalheEscala);
            $detalheEscala->save();

            HistoricoEscala::setHistoricoEscala(
                $detalheEscala->escala,
                StatusEscalaEnum::ATUALIZADA
            );

            DB::commit();

            return response([
                "cod" => 1,
                "msg" => "Macro salva com sucesso.",
                "total_itens" => count($itensCriados),
                "itens_criados" => $itensCriados,
                "retorno" => Escala::buscar($detalheEscala->ESCALA_ID),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response([
                "cod" => 0,
                "msg" => "Erro ao salvar macro: " . $e->getMessage(),
            ], 500);
        }
    }

    private function gerarDiasDoMes($mes, $ano)
    {
        $inicio = Carbon::createFromDate($ano, $mes, 1);
        $fim = $inicio->copy()->endOfMonth();

        return collect(CarbonPeriod::create($inicio, $fim))->values();
    }

    private function removerFeriasEAfastamentos($dias, $detalheEscala)
    {
        $ferias = $detalheEscala->funcionario->ferias->first();
        $afastamento = $detalheEscala->funcionario->afastamentos->first();

        return collect($dias)->filter(function ($dia) use ($ferias, $afastamento) {
            $data = $dia->toDateString();

            $inFerias = $ferias &&
                $data >= $ferias->FERIAS_DATA_INICIO &&
                $data <= $ferias->FERIAS_DATA_FIM;

            $inAfast = $afastamento &&
                $data >= $afastamento->AFASTAMENTO_DATA_INICIO &&
                $data <= $afastamento->AFASTAMENTO_DATA_FIM;

            return !$inFerias && !$inAfast;
        });
    }

    private function criarItemEscala($detalheEscalaId, $turnoId, $data)
    {
        $item = new DetalheEscalaItem();
        $item->DETALHE_ESCALA_ID = $detalheEscalaId;
        $item->TURNO_ID = $turnoId;
        $item->DETALHE_ESCALA_ITEM_DATA = $data;
        $item->DETALHE_ESCALA_ITEM_FALTA = 0;
        $item->DETALHE_ESCALA_ITEM_ATRASO = 0;
        $item->save();

        return [
            'id' => $item->DETALHE_ESCALA_ITEM_ID,
            'data' => $data,
            'turno_id' => $turnoId,
        ];
    }
}
