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
     * Gera o arquivo .txt em formato string para download
     */
    public function gerarRemessa(Folha $folha)
    {
        $linhas = [];

        $detalhes = DetalheFolha::with([
            'funcionario.pessoa',
        ])
            ->where('FOLHA_ID', $folha->FOLHA_ID)
            ->get();

        if ($detalhes->isEmpty()) {
            throw new Exception("Nenhum funcionário encontrado nesta folha para gerar remessa.");
        }

        // 1. HEADER DE ARQUIVO
        $linhas[] = $this->gerarHeaderArquivo();

        // 2. HEADER DE LOTE (Lote de Pagamento de Salários - 30)
        $loteId = 1;
        $linhas[] = $this->gerarHeaderLote($loteId);

        // 3. DETALHES (Segmento A e B por Funcionário)
        $numeroRegistro = 1;
        $totalLiquidoLote = 0.0;

        foreach ($detalhes as $det) {
            $liquido = $det->DETALHE_FOLHA_PROVENTOS - $det->DETALHE_FOLHA_DESCONTOS;

            // Só paga se for maior que zero
            if ($liquido > 0) {
                // Segmento A
                $linhas[] = $this->gerarSegmentoA($loteId, $numeroRegistro++, $det, $liquido);

                // Segmento B (Opcional, mas comum em folhas para CPF/Nome)
                $linhas[] = $this->gerarSegmentoB($loteId, $numeroRegistro++, $det);

                $totalLiquidoLote += $liquido;
            }
        }

        // 4. TRAILER DE LOTE
        $qtdRegistrosLote = $numeroRegistro + 1; // Header(1) + Segmentos + Trailer(1)
        $linhas[] = $this->gerarTrailerLote($loteId, $qtdRegistrosLote, $totalLiquidoLote);

        // 5. TRAILER DE ARQUIVO
        $qtdLotes = 1;
        $qtdRegistrosTotais = $qtdRegistrosLote + 2; // Arquivo Head(1) + Arquivo Trail(1)
        $linhas[] = $this->gerarTrailerArquivo($qtdLotes, $qtdRegistrosTotais);

        return implode("\r\n", $linhas) . "\r\n";
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
