<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FLE + blind index: coluna de busca HMAC e alargamento de PESSOA_CPF_NUMERO para ciphertext.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('PESSOA')) {
            return;
        }

        if (! Schema::hasColumn('PESSOA', 'PESSOA_CPF_HASH')) {
            Schema::table('PESSOA', function (Blueprint $table) {
                $table->string('PESSOA_CPF_HASH', 64)->nullable();
                $table->index('PESSOA_CPF_HASH', 'idx_pessoa_cpf_hash');
            });
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlsrv' && Schema::hasColumn('PESSOA', 'PESSOA_CPF_NUMERO')) {
            try {
                DB::statement('ALTER TABLE PESSOA ALTER COLUMN PESSOA_CPF_NUMERO NVARCHAR(512) NULL');
            } catch (\Throwable $e) {
            }
        } elseif ($driver === 'mysql' && Schema::hasColumn('PESSOA', 'PESSOA_CPF_NUMERO')) {
            try {
                DB::statement('ALTER TABLE PESSOA MODIFY PESSOA_CPF_NUMERO VARCHAR(512) NULL');
            } catch (\Throwable $e) {
            }
        }

        if (Schema::hasColumn('PESSOA', 'PESSOA_CPF')) {
            try {
                if ($driver === 'sqlsrv') {
                    DB::statement('ALTER TABLE PESSOA ALTER COLUMN PESSOA_CPF NVARCHAR(512) NULL');
                } elseif ($driver === 'mysql') {
                    DB::statement('ALTER TABLE PESSOA MODIFY PESSOA_CPF VARCHAR(512) NULL');
                }
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('PESSOA') || ! Schema::hasColumn('PESSOA', 'PESSOA_CPF_HASH')) {
            return;
        }
        Schema::table('PESSOA', function (Blueprint $table) {
            $table->dropIndex('idx_pessoa_cpf_hash');
            $table->dropColumn('PESSOA_CPF_HASH');
        });
    }
};
