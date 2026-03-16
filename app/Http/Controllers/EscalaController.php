<?php

namespace App\Http\Controllers;

use App\Http\Requests\Escala\EscalaCreateRequest;
use App\Http\Requests\Escala\EscalaIdRequest;
use App\Http\Requests\Escala\EscalaUpdateRequest;
use App\Models\Atribuicao;
use App\Models\DetalheEscala;
use App\Models\DetalheEscalaAlerta;
use App\Models\DetalheEscalaItem;
use App\Models\Escala;
use App\Models\Feriado;
use App\Models\Funcionario;
use App\Models\HistoricoEscala;
use App\Models\Setor;
use App\Models\TabelaGenerica;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\Vinculo;
use App\MyLibs\RTG;
use App\MyLibs\StatusEscalaEnum;
use App\MyLibs\TipoEscalaEnum;
use App\MyLibs\VinculoEnum;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EscalaController extends Controller
{
    private $label = 'Escala';

    public function view()
    {
        $setores = Setor::where('SETOR_ATIVO', '=', 1)->orderBy('SETOR_NOME')->get();
        // $turnos = Turno::where('TURNO_ATIVO', '=', 1)->whereNull('TURNO_DATA_EXCLUSAO')->orderBy('TURNO_DESCRICAO')->get();
        // $unidades = Unidade::where("UNIDADE_ATIVA", "=", 1)->orderBy('UNIDADE_NOME')->get();
        $tiposEscalas = TabelaGenerica::tipo_escala();
        // $atribuicoes = Atribuicao::where('ATRIBUICAO_ATIVA', 1)->orderBy('ATRIBUICAO_NOME')->get();
        // $tipoCalculos = TabelaGenerica::listarColunasTabela(RTG::TIPO_CALCULO);
        // $sexos = TabelaGenerica::listarColunasTabela(RTG::SEXO);
        // $vinculos = Vinculo::where('VINCULO_ATIVO', 1)->get();

        // $turnos_selects = array();
        // foreach ($turnos as $turno) {
        //     array_push($turnos_selects, [
        //         'text' => "$turno->TURNO_DESCRICAO | $turno->TURNO_HORA_INICIO até $turno->TURNO_HORA_FIM",
        //         'value' => $turno,
        //     ]);
        // }
        // $turnos = collect($turnos_selects);
        return view('escala.escala_view', compact('setores', 'tiposEscalas'));
    }

    public function avaliacao_view()
    {
        return view('escala.avaliacao_view');
    }

    public function copia_view()
    {
        return view('escala.copia_escala_view');
    }

    public function configurarEscala($id)
    {
        $escala = Escala::buscar($id);

        $this->removerDetalhesEscalaComVinculoExtra($escala);

        $turnos = Turno::where('TURNO_ATIVO', 1)->get();

        $legendas = $turnos->map(function ($turno) {
            return "{$turno->TURNO_DESCRICAO}: ({$turno->TURNO_SIGLA}) - {$turno->TURNO_HORA_INICIO} às {$turno->TURNO_HORA_FIM}";
        })->toArray();

        $funcionarios = Funcionario::with(['lotacoes.atribuicaoLotacoes'])
            ->whereHas('lotacoes', function ($query) use ($escala) {
                $query->whereNull('LOTACAO_DATA_EXCLUSAO')
                    ->where('SETOR_ID', $escala->SETOR_ID);
            })->get();

        foreach ($funcionarios as $funcionario) {
            // Verifica se já existe DetalheEscala para esse funcionário na escala
            $existe = DetalheEscala::where('ESCALA_ID', $escala->ESCALA_ID)
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->exists();

            if ($existe) {
                continue; // Pula para o próximo funcionário
            }

            foreach ($funcionario->lotacoes ?? [] as $lotacao) {

                // Pula lotações de outro setor ou inativas
                if ($lotacao->SETOR_ID !== $escala->SETOR_ID || !is_null($lotacao->LOTACAO_DATA_EXCLUSAO)) {
                    continue;
                }

                // Se a escala for do tipo 1 (Regular), pular vínculos com ID 18 (Extra)
                if ($escala->TIPO_ESCALA_ID == TipoEscalaEnum::REGULAR && $lotacao->VINCULO_ID == VinculoEnum::EXTRA) {
                    continue;
                }

                foreach ($lotacao->atribuicaoLotacoes ?? [] as $atribuicao) {
                    // Verifica se já existe antes de criar
                    $existe = DetalheEscala::where('ESCALA_ID', $escala->ESCALA_ID)
                        ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                        ->where('ATRIBUICAO_ID', $atribuicao->ATRIBUICAO_ID)
                        ->exists();

                    if ($existe)
                        continue;

                    $detalheEscala = new DetalheEscala();
                    $detalheEscala->ESCALA_ID = $escala->ESCALA_ID;
                    $detalheEscala->FUNCIONARIO_ID = $funcionario->FUNCIONARIO_ID;
                    $detalheEscala->ATRIBUICAO_ID = $atribuicao->ATRIBUICAO_ID;
                    $detalheEscala->save();

                    $alerta = new DetalheEscalaAlerta();
                    $alerta->gerarAlerta($detalheEscala);
                }
            }
        }

        // converter para formato 'YYYY-MM-DD' (exemplo: '2025-06-01') para o Carbon entender
        $carbonData = Carbon::createFromFormat('m/Y', $escala->ESCALA_COMPETENCIA);
        $mesAnoFormatado = $carbonData->format('Y-m-d'); // '2025-06-01'

        $feriados = Feriado::buscarFeriadoMesAno($mesAnoFormatado);

        $escala->load([
            'detalheEscalas.escala',
            'detalheEscalas.funcionario.pessoa',
            'detalheEscalas.funcionario.lotacoes.vinculo',
            'detalheEscalas.funcionario.lotacoes.atribuicaoLotacoes',
            'detalheEscalas.atribuicao',
            'detalheEscalas.detalheEscalaItens.turno',
        ]);

        return view('escala.configura_escala_view', compact('escala', 'feriados', 'turnos', 'legendas'));
    }

    public function clonar(EscalaCreateRequest $request)
    {
        try {
            DB::beginTransaction();
            $escala = new Escala($request->post());
            $escala->save();

            $historicoEscala = new HistoricoEscala();
            $historicoEscala->USUARIO_ID = auth()->id();
            $historicoEscala->ESCALA_ID = $escala->ESCALA_ID;
            $historicoEscala->HISTORICO_ESCALA_STATUS = StatusEscalaEnum::CLONADA;
            $historicoEscala->HISTORICO_ESCALA_DATA = date('Y-m-d H:i:s');
            $historicoEscala->HISTORICO_ESCALA_ULTIMO = 1;
            $historicoEscala->save();

            $detalheEscalasJson = $request->post('detalheEscalas');
            if ($detalheEscalasJson) {
                foreach ($detalheEscalasJson as $detalheEscalaRow) {
                    $detalheEscalaObj = new DetalheEscala($detalheEscalaRow);
                    $detalheEscalaObj->ESCALA_ID = $escala->ESCALA_ID;
                    $detalheEscalaObj->save();
                    $detalheEscalaItensJson = $detalheEscalaRow['detalheEscalaItens'];
                    if ($detalheEscalaItensJson) {
                        foreach ($detalheEscalaItensJson as $detalheEScalaItemRow) {
                            $detalheEscalaItemObj = new DetalheEscalaItem($detalheEScalaItemRow);
                            $detalheEscalaItemObj->DETALHE_ESCALA_ID = $detalheEscalaObj->DETALHE_ESCALA_ID;
                            $detalheEscalaItemObj->save();
                        }
                    }
                }
            }
            DB::commit();
            return response(Escala::buscar($escala->ESCALA_ID));
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function inserir(EscalaCreateRequest $request)
    {
        DB::beginTransaction();
        $escala = new Escala($request->input());
        $escala->save();

        HistoricoEscala::setHistoricoEscala($escala, 1);
        DB::commit();

        return response([
            "cod" => 1,
            "msg" => "$this->label adicionado com sucesso",
            "retorno" => Escala::buscarView($escala->ESCALA_ID)
        ], 200);
    }

    public function listar(Request $request)
    {
        $escala = Escala::listar($request)->paginate();

        return response([
            "cod" => 1,
            "msg" => "$this->label listado com sucesso",
            "retorno" => $escala
        ], 200);
    }

    public function listarAvaliacao(Request $request)
    {
        return response(Escala::listarAvaliacao($request));
    }

    public function listarDeferidas(Request $request)
    {
        $escala = Escala::listarDeferidas($request)->paginate();

        return response([
            "cod" => 1,
            "msg" => "$this->label listado com sucesso",
            "retorno" => $escala
        ], 200);
    }

    // public function buscar(Request $request)
    // {
    //     $escala = Escala::buscar($request->id);
    //     return response([
    //         "cod" => 1,
    //         "msg" => "$this->label buscado com sucesso",
    //         "retorno" => $escala
    //     ], 200);
    // }

    // public function pesquisarPorId(EscalaIdRequest $request)
    // {
    //     $escala = Escala::buscarView($request->input('escalaId'));

    //     $escalaInfo = [
    //         "ESCALA_ID" => $escala->ESCALA_ID,
    //         "LABEL" => $escala->setor->SETOR_NOME . ' - ' . $escala->setor->unidade->UNIDADE_NOME . ' - ' . $escala->ESCALA_COMPETENCIA,
    //     ];

    //     return response([
    //         "cod" => 1,
    //         "msg" => "Escala pesquisada com sucesso",
    //         "retorno" => $escalaInfo
    //     ], 200);
    // }

    // public function alterar(EscalaUpdateRequest $request)
    // {
    //     DB::beginTransaction();
    //     $escala = Escala::buscarView($request->ESCALA_ID);
    //     $escala->fill($request->post());
    //     $escala->update();
    //     HistoricoEscala::setHistoricoEscala($escala, 2);
    //     DB::commit();

    //     return response([
    //         "cod" => 1,
    //         "msg" => "$this->label id {$request->ESCALA_ID} alterado com sucesso",
    //         "retorno" => $escala
    //     ], 200);
    // }

    private function removerDetalhesEscalaComVinculoExtra(Escala $escala): void
    {
        if ($escala->TIPO_ESCALA_ID != TipoEscalaEnum::REGULAR) {
            return;
        }

        DB::transaction(function () use ($escala) {
            $detalhesParaRemover = DetalheEscala::where('ESCALA_ID', $escala->ESCALA_ID)
                ->whereHas('funcionario.lotacoes', function ($query) use ($escala) {
                    $query->where('VINCULO_ID', VinculoEnum::EXTRA)
                        ->where('SETOR_ID', $escala->SETOR_ID)
                        ->whereNull('LOTACAO_DATA_EXCLUSAO');
                })->get();

            foreach ($detalhesParaRemover as $detalhe) {
                $detalhe->delete();
            }
        });
    }

    /**
     * Motor da Escala (Fase 1) - Bulk Upsert da Matriz Vue 3
     * Recebe um payload de alterações em lote para determinado Detalhe da Escala
     * e aplica insert otimizado.
     */
    public function salvarMatriz(Request $request)
    {
        $dados = $request->validate([
            'escala_id' => 'required|integer',
            'detalhe_escala_id' => 'required|integer',
            'itens' => 'present|array',
            'itens.*.turno_id' => 'required|integer',
            'itens.*.data' => 'required|date'
        ]);

        $detalheEscalaId = $dados['detalhe_escala_id'];
        $novosItens = $dados['itens'];

        DB::beginTransaction();
        try {
            // 1. Removemos os itens atuais deste funcionário (Sync destrutivo do Grid)
            DetalheEscalaItem::where('DETALHE_ESCALA_ID', $detalheEscalaId)->delete();

            // 2. Preparamos o Array para Bulk Insert
            $insertData = [];
            $now = Carbon::now();
            foreach ($novosItens as $item) {
                $insertData[] = [
                    'DETALHE_ESCALA_ID' => $detalheEscalaId,
                    'TURNO_ID' => $item['turno_id'],
                    'DETALHE_ESCALA_ITEM_DATA' => $item['data'],
                    'DETALHE_ESCALA_ITEM_FALTA' => 0,
                    'DETALHE_ESCALA_ITEM_ATRASO' => 0,
                    // 'created_at' => $now, 'updated_at' => $now // se houvesse timestamps
                ];
            }

            // 3. Otimização: Inserção em Lote (evita N+1 instâncias de Active Record)
            if (count($insertData) > 0) {
                // Chunk se for muito gigante (por precaução, embora um mês tenha ~31 dias)
                foreach (array_chunk($insertData, 100) as $chunk) {
                    DetalheEscalaItem::insert($chunk);
                }
            }

            // 4. Gerar histórico e Alertas
            $detalheEscala = DetalheEscala::with('escala')->find($detalheEscalaId);
            if ($detalheEscala) {
                $alerta = new DetalheEscalaAlerta();
                $alerta->gerarAlerta($detalheEscala);
                HistoricoEscala::setHistoricoEscala($detalheEscala->escala, StatusEscalaEnum::ATUALIZADA);
            }

            DB::commit();

            return response()->json([
                "cod" => 1,
                "msg" => "Escala salva na matriz com sucesso",
                "retorno" => Escala::buscar($dados['escala_id'])
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "cod" => 0,
                "msg" => "Erro no Bulk Upsert da Matriz: " . $e->getMessage()
            ], 500);
        }
    }
}
