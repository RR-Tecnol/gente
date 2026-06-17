<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Âncoras de UNIDADE para TENANT_TYPE GLOBAL_SEMED / GLOBAL_SEMAD:
 * TENANT_ID aponta para UNIDADE_ID real (evita IDs mágicos 0/1).
 * Nomes estáveis para resolução via config + seeder.
 */
return new class extends Migration
{
    public const NOME_ANCORA_SEMED = 'GENTE RBAC ancora GLOBAL SEMED';

    public const NOME_ANCORA_SEMAD = 'GENTE RBAC ancora GLOBAL SEMAD';

    public function up(): void
    {
        if (! Schema::hasTable('UNIDADE')) {
            return;
        }

        $this->ensureUnidade(self::NOME_ANCORA_SEMED, 'RB-GMED');
        $this->ensureUnidade(self::NOME_ANCORA_SEMAD, 'RB-GMAD');
    }

    private function ensureUnidade(string $nome, string $sigla): void
    {
        $exists = DB::table('UNIDADE')->where('UNIDADE_NOME', $nome)->first();
        if ($exists) {
            return;
        }

        $payload = [
            'UNIDADE_NOME' => $nome,
            'UNIDADE_SIGLA' => $sigla,
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

        DB::table('UNIDADE')->insert($payload);
    }

    public function down(): void
    {
        // Sem rollback: remoção poderia violar FKs ou assignments existentes.
    }
};
