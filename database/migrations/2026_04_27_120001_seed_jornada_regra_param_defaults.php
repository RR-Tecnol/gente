<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('JORNADA_REGRA_PARAM')) {
            return;
        }

        $n = (int) DB::table('JORNADA_REGRA_PARAM')->count();
        if ($n > 0) {
            return;
        }

        $now = Carbon::now();
        $d = $now->toDateString();
        $rows = [
            ['JRP_CHAVE' => 'ponto_tolerancia_minutos', 'JRP_VALOR_NUM' => 15, 'JRP_VIGENCIA_INI' => null, 'JRP_VIGENCIA_FIM' => null, 'JRP_OBS' => 'Padrão S3 — sobrescrever com vigência se necessário.'],
            ['JRP_CHAVE' => 'sobreaviso_acionamento_teto_horas', 'JRP_VALOR_NUM' => 24, 'JRP_VIGENCIA_INI' => null, 'JRP_VIGENCIA_FIM' => null, 'JRP_OBS' => 'Teto 24h por janela de acionamento (BRAIN).'],
            ['JRP_CHAVE' => 'sobreaviso_adicional_fracao_hora_normal', 'JRP_VALOR_NUM' => round(1 / 3, 6), 'JRP_VIGENCIA_INI' => null, 'JRP_VIGENCIA_FIM' => null, 'JRP_OBS' => '1/3 da hora normal.'],
            ['JRP_CHAVE' => 'valor_hora_referencia_rs', 'JRP_VALOR_NUM' => 74, 'JRP_VIGENCIA_INI' => null, 'JRP_VIGENCIA_FIM' => null, 'JRP_OBS' => 'Até conciliar com folha / ACT.'],
        ];
        foreach ($rows as $r) {
            DB::table('JORNADA_REGRA_PARAM')->insert(array_merge($r, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('JORNADA_REGRA_PARAM')) {
            return;
        }
        DB::table('JORNADA_REGRA_PARAM')
            ->whereIn('JRP_CHAVE', [
                'ponto_tolerancia_minutos',
                'sobreaviso_acionamento_teto_horas',
                'sobreaviso_adicional_fracao_hora_normal',
                'valor_hora_referencia_rs',
            ])
            ->whereNull('JRP_VIGENCIA_INI')
            ->whereNull('JRP_VIGENCIA_FIM')
            ->delete();
    }
};
