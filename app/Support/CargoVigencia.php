<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Regras de vigência alinhadas ao que o RH precisa rastrear (CBO/escala) sem inventar
 * tabela de histórico: detecta sobreposição de [início, fim] por chave lógica (sigla).
 */
class CargoVigencia
{
    public static function chaveLogica(?string $sigla, string $nome): string
    {
        $s = $sigla !== null ? trim($sigla) : '';
        if ($s !== '') {
            return 'S:' . mb_strtoupper($s, 'UTF-8');
        }
        $n = mb_strtolower(trim($nome), 'UTF-8');

        return 'N:' . $n;
    }

    /** Quando fim nulo, vigência aberta até 9999-12-31 (eSocial costuma exigir fim explícito; aqui é fallback). */
    public static function fimEfetivoParaComparar(?string $fim): string
    {
        $f = $fim !== null ? trim($fim) : '';

        return $f !== '' ? $f : '9999-12-31';
    }

    public static function inicioEfetivo(string $inicio): string
    {
        return $inicio;
    }

    public static function periodosSobrepoem(
        string $aIn, ?string $aFim,
        string $bIn, ?string $bFim
    ): bool {
        $a1 = $aIn;
        $a2 = self::fimEfetivoParaComparar($aFim);
        $b1 = $bIn;
        $b2 = self::fimEfetivoParaComparar($bFim);
        if ($a1 > $a2 || $b1 > $b2) {
            return true;
        }

        return $a1 <= $b2 && $b1 <= $a2;
    }

    /**
     * @return string|null mensagem de erro, ou null se ok
     */
    public static function assertSemSobreposicao(
        string $CARGO_NOME,
        ?string $CARGO_SIGLA,
        string $CARGO_DATA_INICIO,
        ?string $CARGO_DATA_FIM,
        ?int $excluirCargoId = null,
    ): ?string {
        $inicioNovo = substr($CARGO_DATA_INICIO, 0, 10);
        $fimNovo = $CARGO_DATA_FIM && trim($CARGO_DATA_FIM) !== '' ? substr($CARGO_DATA_FIM, 0, 10) : null;

        if ($fimNovo && $fimNovo < $inicioNovo) {
            return 'Data fim de vigência não pode ser anterior à data de início.';
        }

        $chave = self::chaveLogica($CARGO_SIGLA, $CARGO_NOME);
        $sig = $CARGO_SIGLA !== null ? trim($CARGO_SIGLA) : '';
        $nomeN = trim($CARGO_NOME);

        if ($chave[0] === 'N' && $nomeN === '') {
            return 'Defina o nome do cargo (ou a sigla) para validar a vigência.';
        }

        $q = DB::table('CARGO')->select('CARGO_ID', 'CARGO_NOME', 'CARGO_SIGLA', 'CARGO_DATA_INICIO', 'CARGO_DATA_FIM')
            ->whereNotNull('CARGO_DATA_INICIO');
        if ($excluirCargoId !== null) {
            $q->where('CARGO_ID', '!=', $excluirCargoId);
        }
        if ($sig !== '') {
            $q->whereRaw('UPPER(LTRIM(RTRIM(CARGO_SIGLA))) = UPPER(LTRIM(RTRIM(?)))', [$sig]);
        } else {
            $q->where(function ($w) {
                $w->whereNull('CARGO_SIGLA')->orWhere('CARGO_SIGLA', '');
            })->whereRaw("LOWER(LTRIM(RTRIM(CARGO_NOME))) = LOWER(LTRIM(RTRIM(?)))", [$nomeN]);
        }
        $outros = $q->get();
        foreach ($outros as $c) {
            $oi = $c->CARGO_DATA_INICIO ? substr($c->CARGO_DATA_INICIO, 0, 10) : null;
            if (!$oi) {
                continue;
            }
            $of = isset($c->CARGO_DATA_FIM) && $c->CARGO_DATA_FIM && trim($c->CARGO_DATA_FIM) !== ''
                ? substr($c->CARGO_DATA_FIM, 0, 10) : null;
            if (self::periodosSobrepoem($inicioNovo, $fimNovo, $oi, $of)) {
                return 'Já existe vigência conflitante para esta sigla/nome no período informado (CARGO_ID ' . (int) $c->CARGO_ID . '). Ajuste datas ou encerre a vigência anterior (data fim).';
            }
        }

        return null;
    }
}
