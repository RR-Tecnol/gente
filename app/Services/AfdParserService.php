<?php

namespace App\Services;

use App\Models\RegistroPonto;

/**
 * Parser de arquivo AFD (Arquivo Fonte de Dados) — relógios REP-P.
 * Layout conforme Portaria 671/2021 do MTE.
 *
 * Cada linha do AFD tem 100 chars:
 *   Pos 1-9   : NSR (número sequencial)
 *   Pos 10    : Tipo (3 = registro de ponto, 1 = cabeçalho, 9 = trailer)
 *   Pos 11-22 : Data/hora (DDMMAAAAhhmm)
 *   Pos 23-34 : PIS/NIT do funcionário
 *   Pos 35-36 : Tipo de operação (00..23 = marcação)
 */
class AfdParserService
{
    const TIPO_CABECALHO = '1';
    const TIPO_REGISTRO = '3';
    const TIPO_TRAILER = '9';

    private array $erros = [];

    /**
     * Valida o arquivo AFD: verifica sequência de NSR e tipos de registro.
     */
    public function validar(string $conteudo): bool
    {
        $this->erros = [];
        $linhas = $this->linhas($conteudo);
        $nsrAnterior = 0;

        foreach ($linhas as $i => $linha) {
            if (strlen($linha) < 10)
                continue;

            $nsr = (int) substr($linha, 0, 9);
            $tipo = substr($linha, 9, 1);

            if ($tipo === self::TIPO_REGISTRO && $nsr !== $nsrAnterior + 1) {
                $this->erros[] = "Linha " . ($i + 1) . ": NSR fora de sequência (esperado " . ($nsrAnterior + 1) . ", encontrado $nsr)";
            }

            if ($tipo === self::TIPO_REGISTRO) {
                $nsrAnterior = $nsr;
            }
        }

        return empty($this->erros);
    }

    /**
     * Converte as linhas do AFD em array de dados para criar RegistroPonto.
     * @param string $conteudo Conteúdo completo do arquivo AFD
     * @param int|null $funcionarioId Se null, tenta encontrar pelo PIS (não implementado aqui)
     * @return array[] Array de arrays prontos para RegistroPonto::create()
     */
    public function parsear(string $conteudo, ?int $funcionarioId = null): array
    {
        $registros = [];

        foreach ($this->linhas($conteudo) as $linha) {
            if (strlen($linha) < 35)
                continue;
            if (substr($linha, 9, 1) !== self::TIPO_REGISTRO)
                continue;

            $nsr = trim(substr($linha, 0, 9));
            $dataHora = $this->parsearDataHora(substr($linha, 10, 12));
            $pis = trim(substr($linha, 22, 12));

            if (!$dataHora)
                continue;

            $registros[] = [
                'FUNCIONARIO_ID' => $funcionarioId,
                'TERMINAL_ID' => null,
                'REGISTRO_DATA_HORA' => $dataHora,
                'REGISTRO_TIPO' => $this->inferirTipo(count($registros)),
                'REGISTRO_ORIGEM' => 'REP_P',
                'REGISTRO_NSR' => $nsr,
                'REGISTRO_OBSERVACAO' => "PIS: $pis",
            ];
        }

        return $registros;
    }

    public function getErros(): array
    {
        return $this->erros;
    }

    // ──────────────────────────────────────────────
    //  Privados
    // ──────────────────────────────────────────────

    private function linhas(string $conteudo): array
    {
        return array_filter(explode("\n", str_replace("\r", '', $conteudo)));
    }

    private function parsearDataHora(string $s): ?string
    {
        // DDMMAAAAhhmm → AAAA-MM-DD hh:mm:00
        if (strlen($s) < 12)
            return null;
        $d = substr($s, 0, 2);
        $m = substr($s, 2, 2);
        $a = substr($s, 4, 4);
        $h = substr($s, 8, 2);
        $i = substr($s, 10, 2);
        return "$a-$m-$d $h:$i:00";
    }

    /**
     * Infere tipo de batida pela posição (par=ENTRADA, ímpar=SAIDA, simplificado).
     * Em produção, deve-se considerar regras da jornada.
     */
    private function inferirTipo(int $indice): string
    {
        // 0=ENTRADA, 1=PAUSA, 2=RETORNO, 3=SAIDA, e repete
        return ['ENTRADA', 'PAUSA', 'RETORNO', 'SAIDA'][$indice % 4];
    }
}
