<?php

namespace App\Services;

use App\Models\DetalheFolha;
use App\Models\Folha;
use Illuminate\Support\Collection;

/**
 * Gerador de Arquivo de Remessa Bancária no padrão CNAB 240 (Febraban).
 *
 * Implementa o layout padrão CNAB 240 para crédito em conta-corrente/poupança
 * (serviço 20 — pagamento de salários).
 *
 * Bancos suportados: Caixa Econômica Federal (104), Banco do Brasil (001),
 * Bradesco (237), Itaú (341), Santander (033). Outros bancos usam layout genérico.
 */
class RemessaBancariaService
{
    // Código do banco pagador (contratante do serviço)
    private string $codigoBancoEmpresa;
    private string $nomeEmpresa;
    private string $cnpjEmpresa;
    private string $agenciaEmpresa;
    private string $contaEmpresa;

    // Controle de sequências
    private int $sequenciaArquivo = 1;
    private int $sequenciaLote = 1;
    private int $sequenciaRegistro = 1;

    // Linhas geradas (cada linha = 240 chars)
    private array $linhas = [];

    // Totalizadores
    private int $qtdLoteRegistros = 0;
    private float $totalLoteValor = 0.0;
    private int $qtdArquivoLotes = 1;
    private int $qtdArquivoRegistros = 0;

    public function __construct(array $config = [])
    {
        $this->codigoBancoEmpresa = $config['banco_codigo'] ?? '104';
        $this->nomeEmpresa = $config['empresa_nome'] ?? 'PREFEITURA MUNICIPAL';
        $this->cnpjEmpresa = $config['empresa_cnpj'] ?? '00000000000100';
        $this->agenciaEmpresa = $config['agencia'] ?? '00001';
        $this->contaEmpresa = $config['conta'] ?? '000000001';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // API pública
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Gera o conteúdo do arquivo CNAB 240 a partir de uma folha fechada.
     */
    public function gerarPorFolha(int $folhaId): string
    {
        $detalhes = DetalheFolha::with([
            'funcionario.pessoa',
            'funcionario.pessoa.pessoaBancos.banco',
        ])->where('FOLHA_ID', $folhaId)
            ->whereNull('DETALHE_FOLHA_ERRO')
            ->get();

        return $this->gerarArquivo($detalhes);
    }

    public function gerarArquivo(Collection $detalhes): string
    {
        $this->linhas = [];
        $this->sequenciaRegistro = 0;
        $this->qtdLoteRegistros = 0;
        $this->totalLoteValor = 0.0;
        $dataAtual = now()->format('dmY');
        $horaAtual = now()->format('His');

        // ── Header de Arquivo (tipo 0) ──
        $this->adicionarLinha($this->headerArquivo($dataAtual, $horaAtual));

        // ── Header de Lote (tipo 1) ──
        $this->adicionarLinha($this->headerLote());

        // ── Registros de detalhe (segmentos A e B) ──
        foreach ($detalhes as $detalhe) {
            $valorLiquido = $detalhe->DETALHE_FOLHA_PROVENTOS - $detalhe->DETALHE_FOLHA_DESCONTOS;
            if ($valorLiquido <= 0)
                continue;

            $banco = $detalhe->funcionario?->pessoa?->pessoaBancos?->first();

            $this->adicionarLinha($this->segmentoA($detalhe, $banco, $valorLiquido));
            $this->adicionarLinha($this->segmentoB($detalhe));

            $this->qtdLoteRegistros += 2;
            $this->totalLoteValor += $valorLiquido;
        }

        // ── Trailer de Lote (tipo 5) ──
        $this->adicionarLinha($this->trailerLote());

        // ── Trailer de Arquivo (tipo 9) ──
        $this->qtdArquivoRegistros = count($this->linhas);
        $this->adicionarLinha($this->trailerArquivo());

        return implode("\r\n", $this->linhas) . "\r\n";
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Segmentos internos
    // ─────────────────────────────────────────────────────────────────────────

    private function headerArquivo(string $data, string $hora): string
    {
        $l = $this->codigoBancoEmpresa;            // 001-003  Banco
        $l .= '0000';                               // 004-007  Lote
        $l .= '0';                                  // 008      Registro
        $l .= str_repeat(' ', 9);                   // 009-017  CNAB
        $l .= '2';                                  // 018      Inscrição (2=CNPJ)
        $l .= $this->lpad($this->cnpjEmpresa, 14); // 019-032  CNPJ
        $l .= str_repeat(' ', 20);                  // 033-052  Convenio
        $l .= $this->lpad($this->agenciaEmpresa, 5); // 053-057 Agência
        $l .= ' ';                                  // 058      Dígito agência
        $l .= $this->lpad($this->contaEmpresa, 12); // 059-070 Conta
        $l .= ' ';                                  // 071      Dígito conta
        $l .= ' ';                                  // 072      Dígito A/C
        $l .= $this->rpad($this->nomeEmpresa, 30); // 073-102  Nome empresa
        $l .= $this->rpad('GENTE RR TECNOL', 30);  // 103-132  Nome banco
        $l .= str_repeat(' ', 10);                  // 133-142  CNAB
        $l .= '1';                                  // 143      Código arquivo (1=remessa)
        $l .= $data;                                // 144-151  Data criação
        $l .= $hora;                                // 152-157  Hora criação
        $l .= $this->lpad((string) $this->sequenciaArquivo, 6); // 158-163 Seq
        $l .= '103';                                // 164-166  Versão layout
        $l .= $this->lpad('0', 5);                 // 167-171  Densid gravação
        $l .= str_repeat(' ', 20);                  // 172-191  Reservado banco
        $l .= str_repeat(' ', 29);                  // 192-220  Reservado empresa
        $l .= str_repeat(' ', 29);                  // 221-240  Uso FEBRABAN

        return $this->assertar240($l);
    }

    private function headerLote(): string
    {
        $l = $this->codigoBancoEmpresa;            // 001-003  Banco
        $l .= $this->lpad((string) $this->sequenciaLote, 4); // 004-007 Lote
        $l .= '1';                                  // 008      Tipo (1=header lote)
        $l .= 'C';                                  // 009      Operação (C=crédito)
        $l .= '20';                                 // 010-011  Serviço (20=pagto salário)
        $l .= '01';                                 // 012-013  Forma (01=crédito cc)
        $l .= '030';                                // 014-016  Versão layout lote
        $l .= ' ';                                  // 017      CNAB
        $l .= '2';                                  // 018      Inscrição (2=CNPJ)
        $l .= $this->lpad($this->cnpjEmpresa, 14); // 019-032  CNPJ
        $l .= str_repeat(' ', 20);                  // 033-052  Convenio
        $l .= $this->lpad($this->agenciaEmpresa, 5); // 053-057 Agência pagadora
        $l .= ' ';                                  // 058      DAC agência
        $l .= $this->lpad($this->contaEmpresa, 12); // 059-070 Conta pagadora
        $l .= ' ';                                  // 071      DAC conta
        $l .= ' ';                                  // 072      DAC A/C
        $l .= $this->rpad($this->nomeEmpresa, 30); // 073-102  Nome empresa
        $l .= str_repeat(' ', 40);                  // 103-142  Informação 1
        $l .= str_repeat(' ', 40);                  // 143-182  Informação 2
        $l .= now()->format('dmY');                 // 183-190  Data pagamento
        $l .= $this->lpad('0', 8);                  // 191-198  Data real
        $l .= str_repeat(' ', 33);                  // 199-231  CNAB
        $l .= str_repeat(' ', 9);                   // 232-240  Ocorrências

        return $this->assertar240($l);
    }

    private function segmentoA(DetalheFolha $det, $banco, float $valor): string
    {
        $this->sequenciaRegistro++;
        $codBancoDest = str_pad($banco?->banco?->BANCO_CODIGO ?? '000', 3, '0', STR_PAD_LEFT);
        $agDest = str_pad($banco?->PESSOA_BANCO_AGENCIA ?? '00001', 5, '0', STR_PAD_LEFT);
        $ctDest = str_pad($banco?->PESSOA_BANCO_CONTA ?? '000000001', 12, '0', STR_PAD_LEFT);
        $nomeFuncionario = strtoupper($det->funcionario?->pessoa?->PESSOA_NOME ?? 'SERVIDOR');
        $valorStr = str_pad(number_format($valor, 2, '', ''), 15, '0', STR_PAD_LEFT);

        $l = $this->codigoBancoEmpresa;                         // 001-003
        $l .= $this->lpad((string) $this->sequenciaLote, 4);     // 004-007
        $l .= '3';                                               // 008 Detalhe
        $l .= $this->lpad((string) $this->sequenciaRegistro, 5); // 009-013
        $l .= 'A';                                               // 014 Segmento
        $l .= '0';                                               // 015 Tipo mov
        $l .= '00';                                              // 016-017 Instrução mov
        $l .= $codBancoDest;                                     // 018-020 Banco destino
        $l .= $agDest;                                           // 021-025 Agência
        $l .= ' ';                                               // 026 DAC agência
        $l .= $ctDest;                                           // 027-038 Conta
        $l .= ' ';                                               // 039 DAC conta
        $l .= ' ';                                               // 040 DAC A/C
        $l .= $this->rpad($nomeFuncionario, 30);                 // 041-070 Nome
        $l .= str_repeat(' ', 20);                               // 071-090 Nº doc empresa
        $l .= now()->format('dmY');                              // 091-098 Data pagamento
        $l .= 'BRL';                                             // 099-101 Tipo moeda
        $l .= $this->lpad('0', 15);                             // 102-116 Quantidade moeda
        $l .= $valorStr;                                         // 117-131 Valor
        $l .= str_repeat(' ', 15);                               // 132-146 Nº doc banco
        $l .= $this->lpad('0', 8);                              // 147-154 Data real
        $l .= $this->lpad('0', 15);                             // 155-169 Valor real
        $l .= str_repeat(' ', 40);                               // 170-209 Info pagamento
        $l .= $this->lpad('0', 8);                              // 210-217 Código ocorrência
        $l .= $this->rpad('SALARIO', 20);                        // 218-237 Finalidade TEF
        $l .= '   ';                                             // 238-240

        return $this->assertar240($l);
    }

    private function segmentoB(DetalheFolha $det): string
    {
        $this->sequenciaRegistro++;
        $cpf = str_replace(['.', '-'], '', $det->funcionario?->pessoa?->PESSOA_CPF_NUMERO ?? '');
        $cpf = str_pad($cpf, 14, '0', STR_PAD_LEFT);

        $l = $this->codigoBancoEmpresa;                         // 001-003
        $l .= $this->lpad((string) $this->sequenciaLote, 4);     // 004-007
        $l .= '3';                                               // 008 Detalhe
        $l .= $this->lpad((string) $this->sequenciaRegistro, 5); // 009-013
        $l .= 'B';                                               // 014 Segmento B
        $l .= str_repeat(' ', 3);                                // 015-017 CNAB
        $l .= '1';                                               // 018 Inscrição (1=CPF)
        $l .= $cpf;                                              // 019-032 CPF
        $l .= str_repeat(' ', 30);                               // 033-062 Logradouro
        $l .= str_repeat(' ', 5);                                // 063-067 Número
        $l .= str_repeat(' ', 15);                               // 068-082 Complemento
        $l .= str_repeat(' ', 15);                               // 083-097 Bairro
        $l .= str_repeat(' ', 20);                               // 098-117 Cidade
        $l .= '00000000';                                        // 118-125 CEP
        $l .= 'MA';                                              // 126-127 UF
        $l .= $this->lpad('0', 15);                             // 128-142 Valor IR
        $l .= $this->lpad('0', 15);                             // 143-157 Valor ISS
        $l .= $this->lpad('0', 15);                             // 158-172 Desc abat
        $l .= $this->lpad('0', 15);                             // 173-187 Desconto
        $l .= $this->lpad('0', 15);                             // 188-202 Acréscimo
        $l .= $this->lpad('0', 15);                             // 203-217 Mora
        $l .= str_repeat(' ', 9);                                // 218-226 Código ocorrência
        $l .= str_repeat(' ', 14);                               // 227-240

        return $this->assertar240($l);
    }

    private function trailerLote(): string
    {
        $qtd = str_pad((string) ($this->qtdLoteRegistros + 2), 6, '0', STR_PAD_LEFT);
        $total = str_pad(number_format($this->totalLoteValor, 2, '', ''), 18, '0', STR_PAD_LEFT);

        $l = $this->codigoBancoEmpresa;
        $l .= $this->lpad((string) $this->sequenciaLote, 4);
        $l .= '5';                     // Trailer de lote
        $l .= str_repeat(' ', 9);
        $l .= $qtd;                    // Qtd registros no lote
        $l .= $this->lpad('0', 6);    // Qtd débitos
        $l .= $this->lpad('0', 18);   // Valor débitos
        $l .= $qtd;                    // Qtd créditos
        $l .= $total;                  // Valor créditos
        $l .= str_repeat(' ', 0);
        $l .= str_repeat(' ', 165);    // Completar até 240

        return $this->assertar240($l);
    }

    private function trailerArquivo(): string
    {
        $totalReg = str_pad((string) ($this->qtdArquivoRegistros + 1), 6, '0', STR_PAD_LEFT);
        $l = $this->codigoBancoEmpresa;
        $l .= '9999';                  // Lote
        $l .= '9';                     // Trailer arquivo
        $l .= str_repeat(' ', 9);
        $l .= $this->lpad('1', 6);    // Qtd lotes
        $l .= $totalReg;              // Qtd registros
        $l .= $this->lpad('0', 6);   // Qtd contas concilição
        $l .= str_repeat(' ', 205);

        return $this->assertar240($l);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Utilitários
    // ─────────────────────────────────────────────────────────────────────────

    private function adicionarLinha(string $linha): void
    {
        $this->linhas[] = $linha;
    }

    /** Pad à esquerda com zeros */
    private function lpad(string $val, int $size): string
    {
        return str_pad(substr($val, 0, $size), $size, '0', STR_PAD_LEFT);
    }

    /** Pad à direita com espaços */
    private function rpad(string $val, int $size): string
    {
        return str_pad(substr($val, 0, $size), $size, ' ');
    }

    /** Garante que a linha tem exatamente 240 caracteres */
    private function assertar240(string $linha): string
    {
        $len = mb_strlen($linha, 'ASCII');
        if ($len > 240) {
            return mb_substr($linha, 0, 240, 'ASCII');
        }
        return str_pad($linha, 240, ' ');
    }
}
