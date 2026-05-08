<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regra de ouro: SETOR.UNIDADE_ID obrigatório (pré-NOT NULL).
 * Corrige NULL, 0 e valores sem UNIDADE correspondente quando possível.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('SETOR') || ! Schema::hasTable('UNIDADE')) {
            return;
        }

        $anchorNames = [
            'ADMINISTRAÇÃO CENTRAL',
            'ADMINISTRACAO CENTRAL',
            'GABINETE DA SECRETÁRIA',
            'GABINETE DA SECRETARIA',
        ];

        $anchor = DB::table('UNIDADE')
            ->where(function ($q) use ($anchorNames) {
                foreach ($anchorNames as $name) {
                    $q->orWhereRaw('UPPER(LTRIM(RTRIM(UNIDADE_NOME))) = ?', [mb_strtoupper($name, 'UTF-8')]);
                }
            })
            ->orderBy('UNIDADE_ID')
            ->first(['UNIDADE_ID']);

        $anchorId = $anchor ? (int) $anchor->UNIDADE_ID : 0;
        if ($anchorId <= 0) {
            $payload = [
                'UNIDADE_NOME' => 'ADMINISTRAÇÃO CENTRAL',
                'UNIDADE_SIGLA' => 'ADCENT',
            ];
            if (Schema::hasColumn('UNIDADE', 'UNIDADE_ATIVA')) {
                $payload['UNIDADE_ATIVA'] = 1;
            }
            if (Schema::hasColumn('UNIDADE', 'UNIDADE_ATIVO')) {
                $payload['UNIDADE_ATIVO'] = 1;
            }
            if (Schema::hasColumn('UNIDADE', 'UNIDADE_TIPO')) {
                $payload['UNIDADE_TIPO'] = 0;
            } elseif (Schema::hasColumn('UNIDADE', 'TIPO_UNIDADE')) {
                $payload['TIPO_UNIDADE'] = 0;
            }
            $anchorId = (int) DB::table('UNIDADE')->insertGetId($payload);
        }

        if (Schema::hasColumn('SETOR', 'UNIDADE_ID')) {
            DB::table('SETOR')->where(function ($q) {
                $q->whereNull('UNIDADE_ID')->orWhere('UNIDADE_ID', 0);
            })->update(['UNIDADE_ID' => $anchorId]);
        }

        $validIds = DB::table('UNIDADE')->pluck('UNIDADE_ID')->map(fn ($v) => (int) $v)->all();
        if ($validIds !== [] && Schema::hasColumn('SETOR', 'UNIDADE_ID')) {
            DB::table('SETOR')
                ->whereNotNull('UNIDADE_ID')
                ->where('UNIDADE_ID', '!=', 0)
                ->whereNotIn('UNIDADE_ID', $validIds)
                ->update(['UNIDADE_ID' => $anchorId]);
        }
    }

    public function down(): void
    {
        // saneamento de dados: sem rollback automático
    }
};
