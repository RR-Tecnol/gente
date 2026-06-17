<?php

namespace App\Security;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class IpBlocklistService
{
    public static function block(string $ip, string $motivo, int $horas = 24): void
    {
        if (! Schema::hasTable('GENTE_IP_BLOCKLIST') || $ip === '') {
            return;
        }
        $ate = Carbon::now()->addHours($horas);
        try {
            DB::table('GENTE_IP_BLOCKLIST')->insert([
                'IP' => $ip,
                'BLOQUEADO_ATE' => $ate,
                'MOTIVO' => mb_substr($motivo, 0, 120),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('IpBlocklistService: ' . $e->getMessage());
        }
    }

    public static function isBlocked(string $ip): bool
    {
        if (! Schema::hasTable('GENTE_IP_BLOCKLIST') || $ip === '') {
            return false;
        }
        $n = (int) DB::table('GENTE_IP_BLOCKLIST')
            ->where('IP', $ip)
            ->where('BLOQUEADO_ATE', '>', now())
            ->count();

        return $n > 0;
    }
}
