<?php

namespace App\Services\CNAB;

use App\Models\DetalheFolha;
use App\Models\Folha;
use Carbon\Carbon;
use Exception;

class CNAB240Builder
{
    protected $bancoCodigo = '001'; // Banco do Brasil por Padrão
    protected $bancoNome = 'BANCO DO BRASIL S.A.';

    // Dados da Prefeitura/Empresa (Mock)
    protected $empresaInscricao = '2'; // 2 = CNPJ
    protected $empresaCnpj = '00000000000100'; // Sem máscara
    protected $empresaNome = 'PREFEITURA MUNICIPAL DE TESTE';
    protected $agencia = '12345';
    protected $conta = '1234567';
    protected $dac = '8'; // Digito Verificador

    public function __construct($bancoCodigo = '001')
    {
        $this->bancoCodigo = str_pad($bancoCodigo, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Gera o arquivo .txt em formato string (carrega todos os detalhes — usar só para folhas pequenas ou testes).
     */
    public function gerarRemessa(Folha $folha): string
    {
        $detalhes = DetalheFolha::with([
            'funcionario.pessoa',
        ])
            ->where('FOLHA_ID', $folha->FOLHA_ID)
            ->get();

        if ($detalhes->isEmpty()) {
            throw new Exception("Nenhum funcionário encontrado nesta folha para gerar remessa.");
        }

        $linhas = [];
        $this->preencherLinhasRemessa($detalhes, $linhas);

        return implode("\r\n", $linhas) . "\r\n";
    }

    /**
     * Gera CNAB escrevendo linha a linha em $out (ex.: php://output) com {@see DetalheFolha::cursor()} — memória acotada.
     */
    public function streamRemessa(Folha $folha, $out): void
    {
        if (! is_resource($out)) {
            throw new Exception('streamRemessa requer um resource de escrita.');
        }

        $baseQuery = DetalheFolha::with([
            'funcionario.pessoa',
        ])
            ->where('FOLHA_ID', $folha->FOLHA_ID)
            ->orderBy('DETALHE_FOLHA_ID');

        if (! (clone $baseQuery)->exists()) {
            throw new Exception("Nenhum funcionário encontrado nesta folha para gerar remessa.");
        }

        $emit = function (string $linha) use ($out): void {
            fwrite($out, $linha . "\r\n");
        };

        $loteId = 1;
        $emit($this->gerarHeaderArquivo());
        $emit($this->gerarHeaderLote($loteId));

        $numeroRegistro = 1;
        $totalLiquidoLote = 0.0;

        foreach ($baseQuery->cursor() as $det) {
            $liquido = (float) $det->DETALHE_FOLHA_PROVENTOS - (float) $det->DETALHE_FOLHA_DESCONTOS;
            if ($liquido <= 0) {
                continue;
            }
            $emit($this->gerarSegmentoA($loteId, $numeroRegistro++, $det, $liquido));
            $emit($this->gerarSegmentoB($loteId, $numeroRegistro++, $det));
            $totalLiquidoLote += $liquido;
        }

        $qtdRegistrosLote = $numeroRegistro + 1;
        $emit($this->gerarTrailerLote($loteId, $qtdRegistrosLote, $totalLiquidoLote));

        $qtdLotes = 1;
        $qtdRegistrosTotais = $qtdRegistrosLote + 2;
        $emit($this->gerarTrailerArquivo($qtdLotes, $qtdRegistrosTotais));
    }

    /**
     * @param  iterable<int, DetalheFolha>  $detalhes
     * @param  array<int, string>  $linhas
     */
    private function preencherLinhasRemessa(iterable $detalhes, array &$linhas): void
    {
        $linhas[] = $this->gerarHeaderArquivo();

        $loteId = 1;
        $linhas[] = $this->gerarHeaderLote($loteId);

        $numeroRegistro = 1;
        $totalLiquidoLote = 0.0;

        foreach ($detalhes as $det) {
            $liquido = (float) $det->DETALHE_FOLHA_PROVENTOS - (float) $det->DETALHE_FOLHA_DESCONTOS;
            if ($liquido > 0) {
                $linhas[] = $this->gerarSegmentoA($loteId, $numeroRegistro++, $det, $liquido);
                $linhas[] = $this->gerarSegmentoB($loteId, $numeroRegistro++, $det);
                $totalLiquidoLote += $liquido;
            }
        }

        $qtdRegistrosLote = $numeroRegistro + 1;
        $linhas[] = $this->gerarTrailerLote($loteId, $qtdRegistrosLote, $totalLiquidoLote);

        $qtdLotes = 1;
        $qtdRegistrosTotais = $qtdRegistrosLote + 2;
        $linhas[] = $this->gerarTrailerArquivo($qtdLotes, $qtdRegistrosTotais);
    }

    // --- MÉTODOS DE FORMATAÇÃO POSICIONAL (MOCK SIMPLIFICADO P/ PoC) ---

    private function gerarHeaderArquivo()
    {
        // Posição 1 a 3: Banco, 4 a 7: Lote 0000, 8: Registro 0
        $linha = $this->bancoCodigo . '0000' . '0' . str_repeat(' ', 9);
        $linha .= $this->empresaInscricao . str_pad($this->empresaCnpj, 14, '0', STR_PAD_LEFT);
        $linha .= str_pad($this->empresaNome, 30, ' ', STR_PAD_RIGHT);
        $linha .= str_pad($this->bancoNome, 30, ' ', STR_PAD_RIGHT);
        $linha .= Carbon::now()->format('dmY_His');
        return str_pad(substr($linha, 0, 240), 240, ' ', STR_PAD_RIGHT);
    }

    private function gerarHeaderLote($loteId)
    {
        // Posição 1 a 3: Banco, 4 a 7: Lote
        $linha = $this->bancoCodigo . str_pad($loteId, 4, '0', STR_PAD_LEFT) . '1' . 'C' . '30';
        $linha .= str_pad($this->empresaNome, 30, ' ', STR_PAD_RIGHT);
        return str_pad(substr($linha, 0, 240), 240, ' ', STR_PAD_RIGHT);
    }

    private function gerarSegmentoA($loteId, $sequencial, DetalheFolha $det, $liquido)
    {
        // G001, G002, G003...
        $nome = $det->funcionario->pessoa->PESSOA_NOME ?? 'FUNCIONARIO';
        // Remove acentos e converte pra Upper
        $nome = strtoupper(iconv('UTF-8', 'ASCII//TRANSLIT', $nome));

        $valorStr = str_pad(number_format($liquido, 2, '', ''), 15, '0', STR_PAD_LEFT);

        $linha = $this->bancoCodigo . str_pad($loteId, 4, '0', STR_PAD_LEFT) . '3' . str_pad($sequencial, 5, '0', STR_PAD_LEFT) . 'A';
        $linha .= str_pad($nome, 30, ' ', STR_PAD_RIGHT);
        $linha .= $valorStr; // Valor do Pagto
        return str_pad(substr($linha, 0, 240), 240, ' ', STR_PAD_RIGHT);
    }

    private function gerarSegmentoB($loteId, $sequencial, DetalheFolha $det)
    {
        // CPF está diretamente em PESSOA_CPF_NUMERO (com cast), não é relação separada
        $cpfRaw = $det->funcionario->pessoa->PESSOA_CPF_NUMERO ?? '00000000000';
        $cpf = str_pad(preg_replace('/[^0-9]/', '', (string) $cpfRaw), 14, '0', STR_PAD_LEFT);

        $linha = $this->bancoCodigo . str_pad($loteId, 4, '0', STR_PAD_LEFT) . '3' . str_pad($sequencial, 5, '0', STR_PAD_LEFT) . 'B';
        $linha .= '1' . $cpf; // 1 = CPF, 2 = CNPJ
        return str_pad(substr($linha, 0, 240), 240, ' ', STR_PAD_RIGHT);
    }

    private function gerarTrailerLote($loteId, $qtdRegistros, $totalValor)
    {
        $valorStr = str_pad(number_format($totalValor, 2, '', ''), 18, '0', STR_PAD_LEFT);

        $linha = $this->bancoCodigo . str_pad($loteId, 4, '0', STR_PAD_LEFT) . '5' . str_repeat(' ', 9);
        $linha .= str_pad($qtdRegistros, 6, '0', STR_PAD_LEFT); // Qtd Registros no Lote
        $linha .= $valorStr; // Total valor do Lote
        return str_pad(substr($linha, 0, 240), 240, ' ', STR_PAD_RIGHT);
    }

    private function gerarTrailerArquivo($qtdLotes, $qtdRegistrosTotais)
    {
        $linha = $this->bancoCodigo . '9999' . '9' . str_repeat(' ', 9);
        $linha .= str_pad($qtdLotes, 6, '0', STR_PAD_LEFT);
        $linha .= str_pad($qtdRegistrosTotais, 6, '0', STR_PAD_LEFT);
        return str_pad(substr($linha, 0, 240), 240, ' ', STR_PAD_RIGHT);
    }
}
