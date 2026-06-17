<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('SETOR') || ! Schema::hasColumn('SETOR', 'UNIDADE_ID')) {
            return;
        }

        $orphans = (int) DB::table('SETOR')->where(function ($q) {
            $q->whereNull('UNIDADE_ID')->orWhere('UNIDADE_ID', 0);
        })->count();

        if ($orphans > 0) {
            throw new \RuntimeException("SETOR ainda possui {$orphans} registro(s) sem UNIDADE_ID válido. Rode a migration de backfill antes.");
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->dropForeignKeyIfExistsMySql('SETOR', 'UNIDADE_ID');
            DB::statement('ALTER TABLE SETOR MODIFY UNIDADE_ID INT NOT NULL');
            if (Schema::hasTable('UNIDADE') && ! $this->foreignKeyExistsMySql('SETOR', 'FK_SETOR_UNIDADE_RB')) {
                Schema::table('SETOR', function ($table) {
                    $table->foreign('UNIDADE_ID', 'FK_SETOR_UNIDADE_RB')
                        ->references('UNIDADE_ID')
                        ->on('UNIDADE')
                        ->onDelete('no action');
                });
            }
        } elseif ($driver === 'sqlsrv') {
            $this->dropForeignKeyIfExistsSqlSrv('SETOR', 'UNIDADE_ID');
            DB::statement('ALTER TABLE SETOR ALTER COLUMN UNIDADE_ID INT NOT NULL');
            if (Schema::hasTable('UNIDADE') && ! $this->foreignKeyExistsSqlSrv('SETOR', 'FK_SETOR_UNIDADE_RB')) {
                Schema::table('SETOR', function ($table) {
                    $table->foreign('UNIDADE_ID', 'FK_SETOR_UNIDADE_RB')
                        ->references('UNIDADE_ID')
                        ->on('UNIDADE')
                        ->onDelete('no action');
                });
            }
        }
        // sqlite / outros: sem ALTER rígido (testes locais); integridade reforçada na aplicação
    }

    public function down(): void
    {
        // reversão deliberadamente omitida (risco em produção)
    }

    private function dropForeignKeyIfExistsMySql(string $table, string $column): void
    {
        $db = DB::getDatabaseName();
        $rows = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$db, $table, $column]
        );
        foreach ($rows as $row) {
            $name = $row->CONSTRAINT_NAME ?? null;
            if ($name) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
            }
        }
    }

    private function foreignKeyExistsMySql(string $table, string $constraintName): bool
    {
        $db = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT COUNT(1) AS c FROM information_schema.table_constraints WHERE table_schema = ? AND table_name = ? AND constraint_name = ? AND constraint_type = ?',
            [$db, $table, $constraintName, 'FOREIGN KEY']
        );

        return isset($row->c) && (int) $row->c > 0;
    }

    private function foreignKeyExistsSqlSrv(string $table, string $constraintName): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(1) AS c FROM sys.foreign_keys WHERE name = ? AND parent_object_id = OBJECT_ID(?)',
            [$constraintName, $table]
        );

        return isset($row->c) && (int) $row->c > 0;
    }

    private function dropForeignKeyIfExistsSqlSrv(string $table, string $column): void
    {
        $rows = DB::select(
            "SELECT fk.name AS fk_name FROM sys.foreign_keys fk INNER JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id INNER JOIN sys.columns c ON fkc.parent_object_id = c.object_id AND fkc.parent_column_id = c.column_id WHERE OBJECT_NAME(fk.parent_object_id) = ? AND c.name = ?",
            [$table, $column]
        );
        foreach ($rows as $row) {
            $fk = $row->fk_name ?? null;
            if ($fk) {
                DB::statement("ALTER TABLE [{$table}] DROP CONSTRAINT [{$fk}]");
            }
        }
    }
};
