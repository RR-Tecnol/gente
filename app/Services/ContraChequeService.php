<?php

namespace App\Services;

use App\Models\DetalheFolha;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;

class ContraChequeService
{
    /**
     * Gera o Contra-Cheque (Holerite) em PDF para o funcionário na competência especificada.
     */
    public function gerarPDFFuncionario($funcionarioId, $competencia)
    {
        // 1. Busca os dados sintéticos da folha cruzando Funcionario e Eventos
        $detalheFolha = DetalheFolha::with([
            'folha',
            'funcionario.pessoa.cpf',
            'funcionario.lotacoes.setor.unidade',
            'funcionario.lotacoes.vinculo',
            'funcionario.cargo',
            'EventosDetalhesFolhas.evento'
        ])
            ->whereHas('folha', function ($q) use ($competencia) {
                $q->where('FOLHA_COMPETENCIA', $competencia);
            })
            ->where('FUNCIONARIO_ID', $funcionarioId)
            ->first();

        if (!$detalheFolha) {
            throw new Exception("Contra-cheque não encontrado para esta competência.");
        }

        // 2. Separar e Totalizar Proventos vs Descontos
        $proventos = [];
        $descontos = [];

        foreach ($detalheFolha->EventosDetalhesFolhas as $item) {
            $evento = [
                'codigo' => str_pad($item->evento->EVENTO_ID, 4, '0', STR_PAD_LEFT),
                'descricao' => $item->evento->EVENTO_DESCRICAO,
                'referencia' => '00', // Mock da referência (Qtd dias, horas, etc)
                'valor' => number_format($item->EVENTO_DETALHE_FOLHA_VALOR, 2, ',', '.')
            ];

            // BUG-S2-09: usar EVENTO_TIPO (P=Provento, D=Desconto) em vez de strpos no nome
            if (($item->evento->EVENTO_TIPO ?? 'P') === 'D') {
                $descontos[] = $evento;
            } else {
                $proventos[] = $evento;
            }
        }

        // 3. Montar a carga de dados para a View
        $lotacao = $detalheFolha->funcionario->lotacoes->first();

        $dadosGerais = [
            'empresa_nome' => 'PREFEITURA MUNICIPAL DE TESTE',
            'empresa_cnpj' => '00.000.000/0001-00',
            'mes_ano' => substr($competencia, 4, 2) . '/' . substr($competencia, 0, 4), // de 202602 para 02/2026

            'matricula' => str_pad($detalheFolha->FUNCIONARIO_ID, 6, '0', STR_PAD_LEFT),
            'nome' => $detalheFolha->funcionario->pessoa->PESSOA_NOME ?? 'NOME NÃO ENCONTRADO',
            'cpf' => $this->maskCpf($detalheFolha->funcionario->pessoa->cpf->CPF_NUMERO ?? ''),
            'cargo' => $detalheFolha->funcionario->cargo->CARGO_DESCRICAO ?? 'CARGO BASE',
            'lotacao' => $lotacao->setor->SETOR_NOME ?? '' . ' / ' . $lotacao->setor->unidade->UNIDADE_NOME ?? '',
            'admissao' => date('d/m/Y', strtotime($detalheFolha->funcionario->FUNCIONARIO_DATA_ADMISSAO ?? 'now')),

            'proventos' => $proventos,
            'descontos' => $descontos,

            'total_proventos' => number_format($detalheFolha->DETALHE_FOLHA_PROVENTOS, 2, ',', '.'),
            'total_descontos' => number_format($detalheFolha->DETALHE_FOLHA_DESCONTOS, 2, ',', '.'),
            'liquido' => number_format($detalheFolha->DETALHE_FOLHA_LIQUIDO ?? ($detalheFolha->DETALHE_FOLHA_PROVENTOS - $detalheFolha->DETALHE_FOLHA_DESCONTOS), 2, ',', '.'),

            // BUG-S2-11: bases reais do motor em vez de hardcoded
            'base_irrf' => number_format($detalheFolha->DETALHE_BASE_IRRF ?? 0, 2, ',', '.'),
            'base_fgts' => '0,00', // FGTS não se aplica ao RPPS
            'fx_irrf' => '0,00',
            'base_prev' => number_format($detalheFolha->DETALHE_BASE_PREV ?? $detalheFolha->DETALHE_FOLHA_PROVENTOS, 2, ',', '.')
        ];

        // 4. Renderizar o PDF usando a lib Mpdf/DomPDF
        $pdf = Pdf::loadView('pdfs.contra_cheque', compact('dadosGerais'));
        $pdf->setPaper('A4', 'landscape'); // Holerite padrão formulário duplo

        return $pdf;
    }

    private function maskCpf($cpf)
    {
        if (strlen($cpf) === 11) {
            return "***." . substr($cpf, 3, 3) . "." . substr($cpf, 6, 3) . "-**";
        }
        return $cpf;
    }
}
