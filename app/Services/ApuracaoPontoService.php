<?php

namespace App\Services;

use App\Models\ApuracaoPonto;
use App\Models\ConfiguracaoSistema;
use App\Models\DetalheEscalaItem;
use App\Models\EventoDetalheFolha;
use App\Models\RegistroPonto;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Serviço de apuração de ponto eletrônico.
 * Calcula horas trabalhadas, extras e faltas comparando registros com a escala.
 * Automações 1 e 2 do plano: gera eventos na folha ao fechar.
 */
class ApuracaoPontoService
{
    /**
     * Calcula ou atualiza a apuração de um funcionário para uma competência.
     * @param int    $funcionarioId
     * @param string $competencia  "YYYY-MM"
     */
    public function calcular(int $funcionarioId, string $competencia): ApuracaoPonto
    {
        [$ano, $mes] = explode('-', $competencia);

        // Busca todos os registros de ponto do mês
        $registros = RegistroPonto::where('FUNCIONARIO_ID', $funcionarioId)
            ->whereYear('REGISTRO_DATA_HORA', $ano)
            ->whereMonth('REGISTRO_DATA_HORA', $mes)
            ->orderBy('REGISTRO_DATA_HORA')
            ->get();

        // Busca os itens de escala do mês (turnos esperados)
        $itensEscala = DetalheEscalaItem::with('turno')
            ->whereHas('detalheEscala', fn($q) => $q->where('FUNCIONARIO_ID', $funcionarioId))
            ->whereYear('DETALHE_ESCALA_ITEM_DATA', $ano)
            ->whereMonth('DETALHE_ESCALA_ITEM_DATA', $mes)
            ->get()
            ->keyBy('DETALHE_ESCALA_ITEM_DATA');

        $horasTrab = 0.0;
        $horasExtra = 0.0;
        $horasFalta = 0.0;

        // Agrupa registros por dia
        $porDia = $registros->groupBy(fn($r) => Carbon::parse($r->REGISTRO_DATA_HORA)->toDateString());

        // Carregar config individual do funcionário (se existir)
        $pontoConfig = \Illuminate\Support\Facades\DB::table('PONTO_CONFIG_FUNCIONARIO')
            ->where('FUNCIONARIO_ID', $funcionarioId)
            ->first();
        $regimeConfig   = $pontoConfig->REGIME ?? '4_batidas';
        $intervaloConfig = isset($pontoConfig->INTERVALO_ALMOCO) ? (int) $pontoConfig->INTERVALO_ALMOCO : null;

        foreach ($porDia as $dia => $batidas) {
            // Regime 4 batidas: usar entrada1 e saida2 (ignora saida1/entrada2 do almoço)
            // Regime 2 batidas: usar entrada1 e saida1 diretamente
            if ($regimeConfig === '4_batidas') {
                $entradas = $batidas->where('REGISTRO_TIPO', 'ENTRADA')->values();
                $saidas   = $batidas->where('REGISTRO_TIPO', 'SAIDA')->values();
                $entrada  = $entradas->first();
                $saida    = $saidas->last(); // ultima saida do dia
            } else {
                // 2 batidas — comportamento atual (preservado)
                $entrada = $batidas->firstWhere('REGISTRO_TIPO', 'ENTRADA');
                $saida   = $batidas->firstWhere('REGISTRO_TIPO', 'SAIDA');
            }

            if (!$entrada || !$saida)
                continue;

            $brutoMinutos = Carbon::parse($entrada->REGISTRO_DATA_HORA)
                ->diffInMinutes(Carbon::parse($saida->REGISTRO_DATA_HORA));

            $esperado = 0.0;
            $intervaloMinutos = $intervaloConfig ?? 60; // BUG-PONTO-01: desconto padrão de almoço
            if (isset($itensEscala[$dia]) && $itensEscala[$dia]->turno) {
                $turno = $itensEscala[$dia]->turno;
                if ($turno->TURNO_ENTRADA && $turno->TURNO_SAIDA) {
                    $esperadoMinutos = Carbon::parse($turno->TURNO_ENTRADA)
                        ->diffInMinutes(Carbon::parse($turno->TURNO_SAIDA));
                    $intervaloMinutos = $intervaloConfig ?? (int) ($turno->TURNO_INTERVALO_MINUTOS ?? 60);
                    $esperado = ($esperadoMinutos - $intervaloMinutos) / 60;
                }
            }

            // Desconta intervalo de almoço somente se bateu mais que a jornada mínima
            $trabalhado = max(0, ($brutoMinutos - $intervaloMinutos)) / 60;

            $horasTrab += $trabalhado;
            $diff = $trabalhado - $esperado;
            if ($diff > 0)
                $horasExtra += $diff;
            if ($diff < 0)
                $horasFalta += abs($diff);
        }

        // Dias com turno na escala mas sem nenhum registro = falta (desconta intervalo)
        foreach ($itensEscala as $dia => $item) {
            if (!isset($porDia[$dia]) && $item->turno) {
                $turno = $item->turno;
                if ($turno->TURNO_ENTRADA && $turno->TURNO_SAIDA) {
                    $bruto = Carbon::parse($turno->TURNO_ENTRADA)
                        ->diffInMinutes(Carbon::parse($turno->TURNO_SAIDA));
                    $intervalo = $intervaloConfig ?? (int) ($turno->TURNO_INTERVALO_MINUTOS ?? 60); // BUG-PONTO-01
                    $horasFalta += max(0, ($bruto - $intervalo)) / 60;
                }
            }
        }

        // Cria ou atualiza a apuração
        $apuracao = ApuracaoPonto::firstOrNew([
            'FUNCIONARIO_ID' => $funcionarioId,
            'APURACAO_COMPETENCIA' => $competencia,
        ]);

        $apuracao->fill([
            'APURACAO_HORAS_TRAB' => round($horasTrab, 2),
            'APURACAO_HORAS_EXTRA' => round($horasExtra, 2),
            'APURACAO_HORAS_FALTA' => round($horasFalta, 2),
            'APURACAO_STATUS' => $apuracao->APURACAO_STATUS ?? 'ABERTA',
        ])->save();

        return $apuracao;
    }

    /**
     * Fecha a apuração e gera eventos automáticos na folha.
     * Automação 1: HORA_EXTRA → EventoDetalheFolha
     * Automação 2: DESCONTO_FALTA → EventoDetalheFolha
     */
    public function fechar(int $apuracaoId): ApuracaoPonto
    {
        $apuracao = ApuracaoPonto::with(['funcionario'])->findOrFail($apuracaoId);

        if ($apuracao->APURACAO_STATUS === 'FECHADA') {
            return $apuracao;
        }

        // Limite de auto-aprovação de horas extras (Automação 4)
        $limiteAutoAprovacao = (float) ConfiguracaoSistema::get('PONTO_HORAS_EXTRA_AUTOAPROVAR', 0);

        /** @todo Integração real com DetalheFolha — busca o detalhe da folha do mês */
        // Por ora, registra o intenção no log. A integração completa com EventoDetalheFolha
        // requer o DETALHE_FOLHA_ID que vem do processamento da folha.

        $apuracao->update([
            'APURACAO_STATUS' => 'FECHADA',
            'APURACAO_FECHADA_EM' => now(),
            'APURACAO_FECHADA_POR' => Auth::id(),
        ]);

        return $apuracao->fresh();
    }
}
