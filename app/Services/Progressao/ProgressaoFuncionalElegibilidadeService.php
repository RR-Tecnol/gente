<?php

namespace App\Services\Progressao;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProgressaoFuncionalElegibilidadeService
{
    /** @var array<string, object> */
    private array $progConfigCache = [];

    /** @var array<string, Collection> chave carreiraId|classe */
    private array $ordensSalariaisCache = [];

    /** @var array<int, float> */
    private array $cargoSalarioCache = [];

    public function getProgConfig(int|string|null $carreiraId = null): object
    {
        $key = 'c:' . (string) ($carreiraId ?? 'null');
        if (isset($this->progConfigCache[$key])) {
            return $this->progConfigCache[$key];
        }

        $cfg = DB::table('PROGRESSAO_CONFIG')->where('CARREIRA_ID', $carreiraId)->first();
        if (! $cfg) {
            $cfg = DB::table('PROGRESSAO_CONFIG')->whereNull('CARREIRA_ID')->first();
        }

        $this->progConfigCache[$key] = $cfg ?? (object) [
            'CONFIG_INTERSTICIO_MESES' => 24,
            'CONFIG_NOTA_MINIMA' => 7.00,
            'CONFIG_ANUENIO_PCT' => 1.00,
            'CONFIG_REFERENCIA_MAXIMA' => null,
            'CONFIG_CLASSE_FINAL' => null,
            'CONFIG_TEMPO_CLASSE_PROMOCAO_MESES' => 60,
            'CONFIG_ESTAGIO_PROBATORIO_MESES' => 36,
        ];

        return $this->progConfigCache[$key];
    }

    /**
     * Escada de referências da tabela salarial (uma query por par carreira+classe por request).
     */
    public function ordensTabelaSalarial(int|string|null $carreiraId, ?string $classe): Collection
    {
        if ($carreiraId === null || $carreiraId === '' || $classe === null || $classe === '') {
            return collect();
        }
        $k = (string) $carreiraId . '|' . (string) $classe;
        if (! isset($this->ordensSalariaisCache[$k])) {
            if (! Schema::hasTable('TABELA_SALARIAL')) {
                $this->ordensSalariaisCache[$k] = collect();

                return $this->ordensSalariaisCache[$k];
            }
            $this->ordensSalariaisCache[$k] = DB::table('TABELA_SALARIAL')
                ->where('CARREIRA_ID', $carreiraId)
                ->where('TABELA_CLASSE', $classe)
                ->orderBy('TABELA_REFERENCIA_ORDEM')
                ->get();
        }

        return $this->ordensSalariaisCache[$k];
    }

    public function getVencBase(object $func): float
    {
        if (! empty($func->CARREIRA_ID) && ! empty($func->FUNCIONARIO_CLASSE) && ! empty($func->FUNCIONARIO_REFERENCIA)) {
            $ords = $this->ordensTabelaSalarial($func->CARREIRA_ID, $func->FUNCIONARIO_CLASSE);
            $row = $ords->first(fn ($r) => (string) $r->TABELA_REFERENCIA === (string) $func->FUNCIONARIO_REFERENCIA);
            if ($row && isset($row->TABELA_VENCIMENTO_BASE)) {
                return (float) $row->TABELA_VENCIMENTO_BASE;
            }
        }

        $cid = (int) ($func->CARGO_ID ?? 0);
        if (! isset($this->cargoSalarioCache[$cid])) {
            $this->cargoSalarioCache[$cid] = (float) (DB::table('CARGO')->where('CARGO_ID', $cid)->value('CARGO_SALARIO') ?? 0);
        }

        return $this->cargoSalarioCache[$cid];
    }

    public function pickAvaliacaoOrderCol(): ?string
    {
        foreach (['created_at', 'AVALIACAO_DATA', 'updated_at', 'AVALIACAO_ID'] as $c) {
            if (Schema::hasColumn('AVALIACAO_DESEMPENHO', $c)) {
                return $c;
            }
        }

        return null;
    }

    public function extractNota(?object $aval): ?float
    {
        if (! $aval) {
            return null;
        }
        foreach (['AVALIACAO_NOTA', 'NOTA_FINAL', 'AVALIACAO_MEDIA', 'MEDIA_FINAL', 'NOTA'] as $campo) {
            if (isset($aval->$campo) && is_numeric($aval->$campo)) {
                return (float) $aval->$campo;
            }
        }

        return null;
    }

    /**
     * @param  object  $func  pode conter _avaliacao e _com_penalidade pré-carregados
     * @return array{elegivel: bool, elegivel_promocao: bool, bloqueios: list<string>, meses_na_referencia: int, intersticio_exigido: int, nota_obtida: ?float, nota_minima: float, proxima_referencia: mixed, novo_vencimento: ?float}
     */
    public function avaliarEleg(object $func, object $cfg): array
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

        $notaMin = (float) ($cfg->CONFIG_NOTA_MINIMA ?? 7.00);
        $ordAval = $this->pickAvaliacaoOrderCol();
        $aval = $func->_avaliacao ?? (function () use ($func, $ordAval) {
            $q = DB::table('AVALIACAO_DESEMPENHO')->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID);
            if ($ordAval) {
                $q->orderByDesc($ordAval);
            }

            return $q->first();
        })();
        $nota = $this->extractNota($aval);
        if ($nota !== null && (float) $nota < $notaMin) {
            $bloqueios[] = "Nota de avaliação ({$nota}) abaixo do mínimo ({$notaMin}).";
        }

        $pen = isset($func->_com_penalidade)
            ? (bool) $func->_com_penalidade
            : DB::table('AFASTAMENTO')->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->whereRaw("LOWER(AFASTAMENTO_TIPO) LIKE '%disciplinar%' OR LOWER(AFASTAMENTO_TIPO) LIKE '%suspen%'")
                ->where(fn ($q) => $q->whereNull('AFASTAMENTO_DATA_FIM')->orWhere('AFASTAMENTO_DATA_FIM', '>=', now()))
                ->exists();
        if ($pen) {
            $bloqueios[] = 'Penalidade administrativa ativa.';
        }

        $proxRef = null;
        $novoVenc = null;
        $elegProm = false;
        if (! empty($func->CARREIRA_ID) && ! empty($func->FUNCIONARIO_CLASSE) && ! empty($func->FUNCIONARIO_REFERENCIA)) {
            $ords = $this->ordensTabelaSalarial($func->CARREIRA_ID, $func->FUNCIONARIO_CLASSE);
            $idx = $ords->search(fn ($r) => (string) $r->TABELA_REFERENCIA === (string) $func->FUNCIONARIO_REFERENCIA);
            if ($idx !== false && isset($ords[$idx + 1])) {
                $proxRef = $ords[$idx + 1]->TABELA_REFERENCIA;
                $novoVenc = (float) $ords[$idx + 1]->TABELA_VENCIMENTO_BASE;
            } else {
                $elegProm = true;
            }
        }

        return [
            'elegivel' => count($bloqueios) === 0 && ! $elegProm,
            'elegivel_promocao' => $elegProm,
            'bloqueios' => $bloqueios,
            'meses_na_referencia' => $mesesNaRef,
            'intersticio_exigido' => $intersticio,
            'nota_obtida' => $nota,
            'nota_minima' => $notaMin,
            'proxima_referencia' => $proxRef,
            'novo_vencimento' => $novoVenc,
        ];
    }
}
