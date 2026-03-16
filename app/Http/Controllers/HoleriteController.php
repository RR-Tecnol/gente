<?php

namespace App\Http\Controllers;

use App\Models\DetalheFolha;
use App\Models\Funcionario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HoleriteController extends Controller
{
    /**
     * Gera e retorna o PDF do holerite/contra-cheque para o funcionário autenticado.
     * Usuários externos só podem visualizar seus próprios holerites.
     */
    public function pdf(Request $request, int $detalheFolhaId)
    {
        $detalhe = DetalheFolha::with([
            'folha.vinculo',
            'folha.tipoFolha',
            'funcionario.pessoa',
            'funcionario.lotacaoAtual.setor.unidade',
            'funcionario.lotacaoAtual.atribuicao',
            'EventosDetalhesFolhas.evento.incidencia',
            'EventosDetalhesFolhas.evento.historicoEvento.formaCalculo',
        ])->findOrFail($detalheFolhaId);

        // Segurança: usuário externo só acessa seu próprio holerite
        $usuario = Auth::user();
        if ($usuario->perfilExterno) {
            $meuFuncionarioId = $usuario->funcionario?->FUNCIONARIO_ID;
            abort_if($detalhe->FUNCIONARIO_ID !== $meuFuncionarioId, 403, 'Acesso não autorizado.');
        }

        // Separar eventos em proventos e descontos
        $proventos = $detalhe->EventosDetalhesFolhas->filter(
            fn($e) => $e->evento?->incidencia?->DESCRICAO === 'PROVENTO'
        );
        $descontos = $detalhe->EventosDetalhesFolhas->filter(
            fn($e) => $e->evento?->incidencia?->DESCRICAO === 'DESCONTO'
        );

        $totalProventos = $proventos->sum('EVENTO_DETALHE_FOLHA_VALOR');
        $totalDescontos = $descontos->sum('EVENTO_DETALHE_FOLHA_VALOR');
        $totalLiquido = $totalProventos - $totalDescontos;

        $pdf = Pdf::loadView('holerite.holerite_pdf', compact(
            'detalhe',
            'proventos',
            'descontos',
            'totalProventos',
            'totalDescontos',
            'totalLiquido'
        ))->setPaper('a4', 'portrait');

        $competencia = $detalhe->folha->FOLHA_COMPETENCIA ?? 'holerite';
        $nome = $detalhe->funcionario?->pessoa?->PESSOA_NOME ?? 'servidor';
        $nomeArquivo = 'holerite_' . str_replace('/', '-', $competencia) . '_' . str_replace(' ', '_', $nome) . '.pdf';

        return $pdf->stream($nomeArquivo);
    }
}
