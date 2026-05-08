<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('GENTE_ASSIGNMENT')) {
            return;
        }

        Schema::table('GENTE_ASSIGNMENT', function (Blueprint $table) {
            if (! $this->indexExists('GENTE_ASSIGNMENT', 'IX_GENTE_ASN_USR_ATIVO_VIG')) {
                $table->index(['USUARIO_ID', 'ASSIGNMENT_ATIVO', 'VIGENCIA_FIM'], 'IX_GENTE_ASN_USR_ATIVO_VIG');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('GENTE_ASSIGNMENT')) {
            return;
        }
        Schema::table('GENTE_ASSIGNMENT', function (Blueprint $table) {
            if ($this->indexExists('GENTE_ASSIGNMENT', 'IX_GENTE_ASN_USR_ATIVO_VIG')) {
                $table->dropIndex('IX_GENTE_ASN_USR_ATIVO_VIG');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            $db = Schema::getConnection()->getDatabaseName();
            $c = DB::selectOne(
                'SELECT COUNT(1) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
                [$db, $table, $indexName]
            );

            return isset($c->c) && (int) $c->c > 0;
        }

        return false;
    }
};
