<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ServidorProgressaoController extends Controller
{
    public function show(): JsonResponse
    {
        try {
            $user = Auth::user();
            $func = DB::table('FUNCIONARIO as f')
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
                ->leftJoin('CARREIRA as ca', 'ca.CARREIRA_ID', '=', 'f.CARREIRA_ID')
                ->where('f.USUARIO_ID', $user->USUARIO_ID ?? 0)
                ->whereNull('f.FUNCIONARIO_DATA_FIM')
                ->select('f.*', 'p.PESSOA_NOME', 'c.CARGO_NOME', 'c.CARGO_SALARIO', 'ca.CARREIRA_NOME', 'ca.CARREIRA_REGIME')
                ->first();

            if (! $func) {
                return response()->json(['fallback' => true]);
            }

            $cfg = $this->getProgConfig($func->CARREIRA_ID);
            $eleg = $this->avaliarElegibilidade($func, $cfg);
            $venc = $this->getVencBase($func);
            $admissao = $func->FUNCIONARIO_DATA_INICIO ?? null;
            $anos = $admissao ? (int) Carbon::now()->diffInYears(Carbon::parse($admissao)) : 0;
            $anuenio = $venc * (($cfg->CONFIG_ANUENIO_PCT / 100) * $anos);

            $historico = DB::table('HISTORICO_FUNCIONAL')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->orderByDesc('HISTORICO_DATA_EFEITO')
                ->get()
                ->map(fn ($h) => [
                    'id' => $h->HISTORICO_ID,
                    'tipo' => $h->HISTORICO_TIPO,
                    'nivel' => ($h->HISTORICO_CLASSE_DEPOIS ?? '—') . ' — Ref. ' . ($h->HISTORICO_REFERENCIA_DEPOIS ?? '—'),
                    'referencia' => $h->HISTORICO_REFERENCIA_DEPOIS ?? '—',
                    'classe_de' => $h->HISTORICO_CLASSE_ANTES,
                    'ref_de' => $h->HISTORICO_REFERENCIA_ANTES,
                    'classe_para' => $h->HISTORICO_CLASSE_DEPOIS,
                    'ref_para' => $h->HISTORICO_REFERENCIA_DEPOIS,
                    'salario' => $h->HISTORICO_SALARIO_DEPOIS,
                    'salario_de' => $h->HISTORICO_SALARIO_ANTES,
                    'reajuste' => ($h->HISTORICO_SALARIO_ANTES && $h->HISTORICO_SALARIO_DEPOIS && $h->HISTORICO_SALARIO_ANTES > 0)
                        ? round(($h->HISTORICO_SALARIO_DEPOIS - $h->HISTORICO_SALARIO_ANTES) / $h->HISTORICO_SALARIO_ANTES * 100, 1)
                        : 0,
                    'ato' => $h->HISTORICO_ATO_ADMINISTRATIVO,
                    'data' => $h->HISTORICO_DATA_EFEITO,
                    'obs' => $h->HISTORICO_OBSERVACAO,
                    'ativa' => false,
                    'futura' => false,
                ]);

            $ultima = $func->FUNCIONARIO_DATA_ULTIMA_PROGRESSAO ?? $admissao;
            $proxData = $ultima ? Carbon::parse($ultima)->addMonths($cfg->CONFIG_INTERSTICIO_MESES ?? 24)->toDateString() : null;
            $pct = 0;
            if ($ultima && $proxData) {
                $ini = Carbon::parse($ultima);
                $fim = Carbon::parse($proxData);
                $total = $ini->diffInDays($fim);
                $pct = $total > 0 ? min(100, (int) round($ini->diffInDays(now()) / $total * 100)) : 0;
            }

            $progressoes = $historico->toArray();
            $progressoes[] = [
                'id' => 0,
                'tipo' => 'Posição Atual',
                'nivel' => ($func->FUNCIONARIO_CLASSE ?? '—') . ' — Ref. ' . ($func->FUNCIONARIO_REFERENCIA ?? '—'),
                'referencia' => $func->FUNCIONARIO_REFERENCIA ?? '—',
                'salario' => round($venc + $anuenio, 2),
                'data' => $func->FUNCIONARIO_DATA_ULTIMA_PROGRESSAO ?? $admissao,
                'ativa' => true,
                'futura' => false,
                'reajuste' => 0,
                'obs' => null,
            ];
            if ($eleg['proxima_referencia']) {
                $progressoes[] = [
                    'id' => -1,
                    'tipo' => 'Progressão Prevista',
                    'nivel' => ($func->FUNCIONARIO_CLASSE ?? '—') . ' — Ref. ' . $eleg['proxima_referencia'],
                    'referencia' => $eleg['proxima_referencia'],
                    'salario' => $eleg['novo_vencimento'],
                    'data' => $proxData,
                    'ativa' => false,
                    'futura' => true,
                    'reajuste' => ($venc > 0 ? round(($eleg['novo_vencimento'] - $venc) / $venc * 100, 1) : 0),
                    'obs' => 'Progressão horizontal automática (interstício de ' . ($cfg->CONFIG_INTERSTICIO_MESES ?? 24) . ' meses)',
                ];
            }

            return response()->json([
                'fallback' => false,
                'nome' => $func->PESSOA_NOME,
                'cargo' => $func->CARGO_NOME ?? '—',
                'carreira' => $func->CARREIRA_NOME ?? null,
                'classe' => $func->FUNCIONARIO_CLASSE ?? '—',
                'referencia' => $func->FUNCIONARIO_REFERENCIA ?? '—',
                'estavel' => (bool) ($func->FUNCIONARIO_ESTAVEL ?? false),
                'estagio' => (bool) ($func->FUNCIONARIO_ESTAGIO_PROBATORIO ?? false),
                'admissao' => $admissao,
                'anos_servico' => $anos,
                'salario_base' => $venc,
                'vencimento_base' => $venc,
                'anuenio' => round($anuenio, 2),
                'salario_total' => round($venc + $anuenio, 2),
                'elegibilidade' => $eleg,
                'proxima_data' => $proxData,
                'pct_para_proxima' => $pct,
                'historico' => $historico,
                'progressoes' => $progressoes,
                'config' => [
                    'intersticio' => $cfg->CONFIG_INTERSTICIO_MESES ?? 24,
                    'nota_minima' => $cfg->CONFIG_NOTA_MINIMA ?? 7.00,
                    'anuenio_pct' => $cfg->CONFIG_ANUENIO_PCT ?? 1.00,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'erro' => $e->getMessage()]);
        }
    }

    private function getProgConfig($carreiraId = null): object
    {
        $cfg = DB::table('PROGRESSAO_CONFIG')->where('CARREIRA_ID', $carreiraId)->first();
        if (! $cfg) {
            $cfg = DB::table('PROGRESSAO_CONFIG')->whereNull('CARREIRA_ID')->first();
        }

        return $cfg ?? (object) [
            'CONFIG_INTERSTICIO_MESES' => 24,
            'CONFIG_NOTA_MINIMA' => 7.00,
            'CONFIG_ANUENIO_PCT' => 1.00,
        ];
    }

    private function getVencBase(object $func): float
    {
        if (! empty($func->CARREIRA_ID) && ! empty($func->FUNCIONARIO_CLASSE) && ! empty($func->FUNCIONARIO_REFERENCIA)) {
            $v = DB::table('TABELA_SALARIAL')
                ->where('CARREIRA_ID', $func->CARREIRA_ID)
                ->where('TABELA_CLASSE', $func->FUNCIONARIO_CLASSE)
                ->where('TABELA_REFERENCIA', $func->FUNCIONARIO_REFERENCIA)
                ->value('TABELA_VENCIMENTO_BASE');
            if ($v) {
                return (float) $v;
            }
        }

        return (float) (DB::table('CARGO')->where('CARGO_ID', $func->CARGO_ID ?? 0)->value('CARGO_SALARIO') ?? 0);
    }

    private function avaliarElegibilidade(object $func, object $cfg): array
    {
        $bloqueios = [];
        if (isset($func->CARREIRA_REGIME) && $func->CARREIRA_REGIME === 'comissionado') {
            $bloqueios[] = 'Cargo comissionado não tem progressão funcional.';
        }
        if (! empty($func->FUNCIONARIO_ESTAGIO_PROBATORIO)) {
            $bloqueios[] = 'Servidor em estágio probatório.';
        }
        $ultima = $func->FUNCIONARIO_DATA_ULTIMA_PROGRESSAO ?? $func->FUNCIONARIO_DATA_INICIO ?? null;
        $mesesNaRef = $ultima ? (int) Carbon::now()->diffInMonths(Carbon::parse($ultima)) : 0;
        $intersticio = (int) ($cfg->CONFIG_INTERSTICIO_MESES ?? 24);
        if ($mesesNaRef < $intersticio) {
            $bloqueios[] = 'Interstício não cumprido. Faltam ' . ($intersticio - $mesesNaRef) . ' meses.';
        }

        return [
            'elegivel' => count($bloqueios) === 0,
            'elegivel_promocao' => false,
            'bloqueios' => $bloqueios,
            'meses_na_referencia' => $mesesNaRef,
            'intersticio_exigido' => $intersticio,
            'nota_obtida' => null,
            'nota_minima' => (float) ($cfg->CONFIG_NOTA_MINIMA ?? 7.00),
            'proxima_referencia' => null,
            'novo_vencimento' => null,
        ];
    }
}

