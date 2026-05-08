<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Garante cabeçalho {@see FOLHA} para uma competência (YYYY-MM ou YYYYMM / MMYYYY legado),
 * tolerante a colunas opcionais no SQL Server legado.
 */
final class FolhaCompetenciaCabecalho
{
    /**
     * @throws \InvalidArgumentException
     */
    public static function normalizarCompetenciaDb(string $comp): string
    {
        $comp = trim($comp);
        if (preg_match('/^(\d{4})-(\d{2})$/', $comp, $m)) {
            return $m[1].$m[2];
        }
        $digits = preg_replace('/\D/', '', $comp);
        if (strlen($digits) === 6) {
            $a = (int) substr($digits, 0, 4);
            $b = (int) substr($digits, 4, 2);
            if ($a >= 1900 && $a <= 2100 && $b >= 1 && $b <= 12) {
                return $digits;
            }
            $mth = (int) substr($digits, 0, 2);
            $yr = (int) substr($digits, 2, 4);
            if ($mth >= 1 && $mth <= 12 && $yr >= 1900 && $yr <= 2100) {
                return sprintf('%04d%02d', $yr, $mth);
            }
        }

        throw new \InvalidArgumentException('Competência inválida: '.$comp);
    }

    /**
     * Localiza ou cria o registro FOLHA para a competência (armazenamento preferencial YYYYMM).
     *
     * @return object registo FOLHA (stdClass)
     */
    public static function obterOuCriarPorCompetencia(string $compFromRequest): object
    {
        if (! Schema::hasTable('FOLHA')) {
            throw new \RuntimeException('Tabela FOLHA não existe neste ambiente.');
        }

        $compDb = self::normalizarCompetenciaDb($compFromRequest);
        $legacy = substr($compDb, 4, 2).substr($compDb, 0, 4);

        $folha = DB::table('FOLHA')
            ->whereIn('FOLHA_COMPETENCIA', [$compDb, $legacy])
            ->orderByDesc('FOLHA_ID')
            ->first();

        if ($folha) {
            return $folha;
        }

        $cols = Schema::getColumnListing('FOLHA');
        $yy = substr($compDb, 0, 4);
        $mm = substr($compDb, 4, 2);
        $meses = [
            '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
            '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
            '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro',
        ];
        $label = 'Folha '.($meses[$mm] ?? $mm).'/'.$yy;

        $row = ['FOLHA_COMPETENCIA' => $compDb];

        if (in_array('FOLHA_DESCRICAO', $cols, true)) {
            $row['FOLHA_DESCRICAO'] = $label;
        }

        if (in_array('FOLHA_STATUS', $cols, true)) {
            $sample = DB::table('FOLHA')->whereNotNull('FOLHA_STATUS')->value('FOLHA_STATUS');
            if ($sample !== null && is_numeric($sample)) {
                $row['FOLHA_STATUS'] = 1;
            } else {
                $row['FOLHA_STATUS'] = 'Aberta';
            }
        }

        if (in_array('FOLHA_SITUACAO', $cols, true)) {
            $row['FOLHA_SITUACAO'] = 'A';
        }

        if (in_array('FOLHA_ATIVO', $cols, true)) {
            $row['FOLHA_ATIVO'] = 1;
        }

        if (in_array('FOLHA_QTD_SERVIDORES', $cols, true)) {
            $row['FOLHA_QTD_SERVIDORES'] = 0;
        }

        if (in_array('FOLHA_VALOR_TOTAL', $cols, true)) {
            $row['FOLHA_VALOR_TOTAL'] = 0;
        }

        if (in_array('FOLHA_TIPO', $cols, true)) {
            $row['FOLHA_TIPO'] = 1;
        }

        if (in_array('VINCULO_ID', $cols, true) && Schema::hasTable('VINCULO')) {
            $vid = DB::table('VINCULO')->orderBy('VINCULO_ID')->value('VINCULO_ID');
            if ($vid !== null) {
                $row['VINCULO_ID'] = (int) $vid;
            }
        }

        if (in_array('SETOR_ID', $cols, true) && Schema::hasTable('SETOR')) {
            $sid = DB::table('SETOR')->orderBy('SETOR_ID')->value('SETOR_ID');
            if ($sid !== null) {
                $row['SETOR_ID'] = (int) $sid;
            }
        }

        if (in_array('updated_at', $cols, true)) {
            $row['updated_at'] = now();
        }
        if (in_array('created_at', $cols, true)) {
            $row['created_at'] = now();
        }

        $id = (int) DB::table('FOLHA')->insertGetId($row);
        $criada = DB::table('FOLHA')->where('FOLHA_ID', $id)->first();
        if (! $criada) {
            throw new \RuntimeException('Falha ao criar cabeçalho da folha.');
        }

        return $criada;
    }
}
