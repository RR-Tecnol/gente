<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('SETOR') || !Schema::hasTable('UNIDADE')) {
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
            if (Schema::hasColumn('UNIDADE', 'UNIDADE_TIPO')) {
                $payload['UNIDADE_TIPO'] = 0;
            } elseif (Schema::hasColumn('UNIDADE', 'TIPO_UNIDADE')) {
                $payload['TIPO_UNIDADE'] = 0;
            }

            $anchorId = (int) DB::table('UNIDADE')->insertGetId($payload);
        }

        DB::table('SETOR')
            ->where('UNIDADE_ID', 0)
            ->update(['UNIDADE_ID' => $anchorId]);
    }

    public function down(): void
    {
        // Migração de saneamento: rollback automático desabilitado por segurança.
    }
};

