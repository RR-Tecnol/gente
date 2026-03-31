<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ConsigGeradorService
{
    /**
     * Gera o conteúdo de um arquivo de remessa SAIDA para uma operadora.
     * Retorna array com: conteudo (string), nome_arquivo, total_registros.
     */
    public function gerar(int $consignatariaId, string $tipo, string $competencia): array
    {
        // 1. Localizar layout SAIDA correspondente ao tipo solicitado
        $nomePadrao = 'NEOCONSIG_' . strtoupper($tipo);

        $layout = DB::table('LAYOUT_CONSIGNATARIA')
            ->where('CONSIGNATARIA_ID', $consignatariaId)
            ->where('LAYOUT_DIRECAO', 'SAIDA')
            ->where('LAYOUT_ATIVO', true)
            ->where('LAYOUT_NOME', $nomePadrao)
            ->first();

        if (!$layout) {
            // Fallback: qualquer layout SAIDA disponível para a operadora
            $layout = DB::table('LAYOUT_CONSIGNATARIA')
                ->where('CONSIGNATARIA_ID', $consignatariaId)
                ->where('LAYOUT_DIRECAO', 'SAIDA')
                ->where('LAYOUT_ATIVO', true)
                ->first();
        }

        if (!$layout) {
            throw new \RuntimeException("Nenhum layout SAIDA encontrado para esta operadora.");
        }

        $mapeamento   = $layout->LAYOUT_MAPEAMENTO
            ? json_decode($layout->LAYOUT_MAPEAMENTO, true)
            : [];
        $tamanhoLinha = (int) ($layout->LAYOUT_TAMANHO_LINHA ?? 66);
        $encoding     = $layout->LAYOUT_ENCODING ?? 'UTF-8';

        // 2. Buscar contratos ativos na competência
        // Competência formato AAAAMM → converter para período
        $ano = (int) substr($competencia, 0, 4);
        $mes = (int) substr($competencia, 4, 2);
        $inicioMes = sprintf('%04d-%02d-01', $ano, $mes);
        $fimMes    = date('Y-m-t', strtotime($inicioMes));

        $contratos = DB::table('CONSIG_CONTRATO as c')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'c.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->where('c.STATUS', 'ATIVO')
            ->where('c.DATA_INICIO', '<=', $fimMes)
            ->where(function ($q) use ($fimMes, $inicioMes) {
                $q->whereNull('c.DATA_FIM')
                  ->orWhere('c.DATA_FIM', '>=', $inicioMes);
            })
            ->select(
                'f.FUNCIONARIO_MATRICULA as matricula',
                'p.PESSOA_NOME as nome',
                'c.CONTRATO_ID as id_operacao',
                'c.VALOR_PARCELA as valor',
                'c.SALDO_DEVEDOR as saldo_devedor',
                'c.PRAZO_MESES as prazo_total',
                DB::raw('(c.PRAZO_MESES - c.PARCELAS_PAGAS) as prazo_restante')
            )
            ->get();

        // 3. Montar linhas do arquivo
        $linhas = [];

        foreach ($contratos as $contrato) {
            // Criar linha em branco com o tamanho correto
            $linha = str_repeat(' ', $tamanhoLinha);

            if (empty($mapeamento)) {
                // Sem mapeamento: linha simples com matrícula e valor
                $linha = str_pad((string) $contrato->matricula, 10)
                       . str_pad(number_format($contrato->valor, 2, '.', ''), 15, '0', STR_PAD_LEFT);
            } else {
                foreach ($mapeamento as $campo => $posicoes) {
                    if (!is_array($posicoes) || count($posicoes) < 2) continue;
                    [$ini, $fim] = $posicoes;
                    $tamanho = $fim - $ini + 1;

                    $valor = match($campo) {
                        'matricula'      => str_pad((string) ($contrato->matricula ?? ''), $tamanho, ' '),
                        'nome'           => str_pad(mb_substr((string) ($contrato->nome ?? ''), 0, $tamanho), $tamanho, ' '),
                        'rubrica'        => str_pad('0099', $tamanho, '0', STR_PAD_LEFT), // rubrica padrão consignação
                        'competencia'    => str_pad($competencia, $tamanho, ' '),
                        'valor',
                        'valor_parcela'  => str_pad(
                                                str_replace('.', '', number_format((float)($contrato->valor ?? 0), 2, '.', '')),
                                                $tamanho, '0', STR_PAD_LEFT
                                            ),
                        'saldo_devedor'  => str_pad(
                                                str_replace('.', '', number_format((float)($contrato->saldo_devedor ?? 0), 2, '.', '')),
                                                $tamanho, '0', STR_PAD_LEFT
                                            ),
                        'prazo_total'    => str_pad((string) ($contrato->prazo_total ?? 0), $tamanho, '0', STR_PAD_LEFT),
                        'prazo_restante' => str_pad((string) ($contrato->prazo_restante ?? 0), $tamanho, '0', STR_PAD_LEFT),
                        'id_operacao'    => str_pad((string) ($contrato->id_operacao ?? ''), $tamanho, '0', STR_PAD_LEFT),
                        default          => str_repeat(' ', $tamanho),
                    };

                    // Truncar se passou do tamanho e inserir na posição correta (1-based → 0-based)
                    $valor = mb_substr($valor, 0, $tamanho);
                    $linha = substr_replace($linha, $valor, $ini - 1, $tamanho);
                }
            }

            $linhas[] = $linha;
        }

        $conteudo = implode("\r\n", $linhas);

        // 4. Converter encoding se necessário
        if (strtoupper($encoding) !== 'UTF-8') {
            $conteudo = mb_convert_encoding($conteudo, $encoding, 'UTF-8');
        }

        return [
            'conteudo'        => $conteudo,
            'nome_arquivo'    => strtolower("{$nomePadrao}_{$competencia}.txt"),
            'total_registros' => count($linhas),
            'layout_nome'     => $layout->LAYOUT_NOME,
        ];
    }
}
