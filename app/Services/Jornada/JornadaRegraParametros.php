<?php

namespace App\Services\Jornada;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S3.1 — parâmetros legais com vigência (fallback: config/jornada.php).
 */
class JornadaRegraParametros
{
    public const CHAVE_PONTO_TOLERANCIA_MIN = 'ponto_tolerancia_minutos';

    public const CHAVE_SOBREAVISO_TETO_H = 'sobreaviso_acionamento_teto_horas';

    public const CHAVE_SOBREAVISO_FRACAO = 'sobreaviso_adicional_fracao_hora_normal';

    public const CHAVE_VALOR_HORA_REF = 'valor_hora_referencia_rs';

    public static function toleranciaPontoMinutos(?DateTimeInterface $em = null): float
    {
        $em = $em ? Carbon::instance($em) : now();

        return (float) self::valorVigente(self::CHAVE_PONTO_TOLERANCIA_MIN, $em, (float) config('jornada.ponto_tolerancia_minutos', 15));
    }

    public static function tetoSobreavisoAcionamentoHoras(?DateTimeInterface $em = null): float
    {
        $em = $em ? Carbon::instance($em) : now();

        return (float) self::valorVigente(self::CHAVE_SOBREAVISO_TETO_H, $em, (float) config('jornada.sobreaviso_acionamento_teto_horas', 24));
    }

    public static function fracaoAdicionalSobreHoraNormal(?DateTimeInterface $em = null): float
    {
        $em = $em ? Carbon::instance($em) : now();

        return (float) self::valorVigente(
            self::CHAVE_SOBREAVISO_FRACAO,
            $em,
            (float) config('jornada.sobreaviso_adicional_fracao_hora_normal', 1 / 3)
        );
    }

    public static function valorHoraReferenciaRs(?DateTimeInterface $em = null): float
    {
        $em = $em ? Carbon::instance($em) : now();

        return (float) self::valorVigente(self::CHAVE_VALOR_HORA_REF, $em, (float) config('jornada.valor_hora_referencia_rs', 74.0));
    }

    /**
     * Valor sugerido para acionamento: duração (h) × VHN × (1/3) — alinhado ao BRAIN jornada §4.
     */
    public static function valorAdicionalHoraFracionada(float $duracaoHoras, float $valorHora, float $fracaoDaHoraNormal): float
    {
        return round(max(0.0, $duracaoHoras) * $valorHora * $fracaoDaHoraNormal, 2);
    }

    public static function valorSugeridoAcionamentoSobreaviso(float $duracaoHoras, ?DateTimeInterface $em = null): float
    {
        $em = $em ? Carbon::instance($em) : now();

        return self::valorAdicionalHoraFracionada(
            $duracaoHoras,
            self::valorHoraReferenciaRs($em),
            self::fracaoAdicionalSobreHoraNormal($em)
        );
    }

    /**
     * Lê tabela JORNADA_REGRA_PARAM; se vazia ou sem linha na data, devolve $padrao.
     */
    public static function valorVigente(string $chave, DateTimeInterface $em, float $padrao): float
    {
        if (!Schema::hasTable('JORNADA_REGRA_PARAM')) {
            return $padrao;
        }

        $d = Carbon::instance($em)->toDateString();

        $row = DB::table('JORNADA_REGRA_PARAM')
            ->where('JRP_CHAVE', $chave)
            ->where(function ($q) use ($d) {
                $q->whereNull('JRP_VIGENCIA_INI')->orWhere('JRP_VIGENCIA_INI', '<=', $d);
            })
            ->where(function ($q) use ($d) {
                $q->whereNull('JRP_VIGENCIA_FIM')->orWhere('JRP_VIGENCIA_FIM', '>=', $d);
            })
            ->orderByDesc('JRP_VIGENCIA_INI')
            ->first();

        if ($row && isset($row->JRP_VALOR_NUM)) {
            return (float) $row->JRP_VALOR_NUM;
        }

        return $padrao;
    }
}
