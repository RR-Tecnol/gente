<?php

namespace App\Http\Controllers;

use App\Models\DetalheFolha;
use App\Models\Usuario;
use App\Services\ContraChequeService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContraChequeController extends Controller
{
    protected $contraChequeService;

    public function __construct(ContraChequeService $contraChequeService)
    {
        $this->contraChequeService = $contraChequeService;
    }

    /**
     * Retorna a lista de competências disponíveis (holerites) para o servidor autenticado.
     */
    public function listarMinhasFolhas(Request $request)
    {
        try {
            $authId = Auth::id();

            // Estratégia 1: busca via FUNCIONARIO_ID direto no registro do usuario (mais rápido)
            $user = Usuario::with('funcionario')->find($authId);
            $funcionarioId = $user->FUNCIONARIO_ID ?? null;

            // Estratégia 2: fallback via CPF (pessoaVinculada) caso não haja FUNCIONARIO_ID direto
            if (!$funcionarioId) {
                $user->load('pessoaVinculada.funcionarios');
                if ($user->pessoaVinculada && !$user->pessoaVinculada->funcionarios->isEmpty()) {
                    $funcionarioId = $user->pessoaVinculada->funcionarios->first()->FUNCIONARIO_ID;
                }
            }

            if (!$funcionarioId) {
                return response()->json(['erro' => 'Nenhum vínculo profissional localizado para o usuário logado.'], 403);
            }

            // Busca os detalhes financeiros desse funcionário em todas as competências
            $holerites = DetalheFolha::with(['folha', 'EventosDetalhesFolhas.evento'])
                ->where('FUNCIONARIO_ID', $funcionarioId)
                ->get();

            \Illuminate\Support\Facades\Log::info("ContraChequeController processou a busca", [
                'funcionarioId' => $funcionarioId,
                'count' => $holerites->count()
            ]);

            $holerites = $holerites->map(function ($detalhe) {
                $proventos = (float) $detalhe->DETALHE_FOLHA_PROVENTOS;
                $descontos = (float) $detalhe->DETALHE_FOLHA_DESCONTOS;

                if ($proventos == 0 && $descontos == 0 && $detalhe->EventosDetalhesFolhas) {
                    foreach ($detalhe->EventosDetalhesFolhas as $evDet) {
                        if ($evDet->evento && $evDet->evento->EVENTO_TIPO === 'P') {
                            $proventos += (float) $evDet->EVENTO_DETALHE_FOLHA_VALOR;
                        } elseif ($evDet->evento && $evDet->evento->EVENTO_TIPO === 'D') {
                            $descontos += (float) $evDet->EVENTO_DETALHE_FOLHA_VALOR;
                        }
                    }
                }

                return [
                    'funcionario_id' => $detalhe->FUNCIONARIO_ID,
                    'detalhe_folha_id' => $detalhe->DETALHE_FOLHA_ID,
                    'competencia' => $detalhe->folha->FOLHA_COMPETENCIA ?? 'N/D',
                    'data_processamento' => $detalhe->folha->FOLHA_CRIACAO ?? null,
                    'proventos' => $proventos,
                    'descontos' => $descontos,
                    'liquido' => $proventos - $descontos,
                ];
            })
                ->filter(function ($holerite) {
                    return $holerite['competencia'] !== 'N/D' || $holerite['liquido'] > 0;
                })
                ->sortByDesc('competencia')
                ->values();

            return response()->json($holerites);

        } catch (Exception $e) {
            return response()->json([
                'msg' => 'Erro ao consultar competências: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna o PDF para Download do Contra-cheque
     * URL Esperada: /contra-cheque/{funcionario_id}/{competencia}/pdf
     * Exemplo: /contra-cheque/152/202602/pdf
     */
    public function emitirPdf(Request $request, $funcionarioId, $competencia)
    {
        try {
            // Verifica permissões: Se for EXTERNO, só pode baixar o seu.
            $user = Usuario::with('pessoaVinculada.funcionarios')->find(Auth::id());

            if ($user && $user->perfilExterno) {
                $ehDonoDoHolerite = false;
                if ($user->pessoaVinculada) {
                    foreach ($user->pessoaVinculada->funcionarios as $func) {
                        if ($func->FUNCIONARIO_ID == $funcionarioId) {
                            $ehDonoDoHolerite = true;
                            break;
                        }
                    }
                }

                if (!$ehDonoDoHolerite) {
                    abort(403, "Acesso Negado. Você só pode emitir seus próprios contra-cheques.");
                }
            }

            $pdf = $this->contraChequeService->gerarPDFFuncionario($funcionarioId, $competencia);

            return $pdf->stream("ContraCheque_{$funcionarioId}_{$competencia}.pdf");

        } catch (Exception $e) {
            return response()->json([
                'cod' => 0,
                'msg' => 'Erro ao emitir Contra-cheque: ' . $e->getMessage()
            ], 404);
        }
    }
}
