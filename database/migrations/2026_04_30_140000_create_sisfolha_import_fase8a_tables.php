<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 8A — Staging, run, linhas e de-para cargo SISFOLHA → GENTE.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('sisfolha_import_runs')) {
            Schema::create('sisfolha_import_runs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('file_name', 512)->nullable();
                $table->string('file_checksum', 128)->nullable();
                $table->unsignedBigInteger('operator_usuario_id')->nullable();
                $table->string('status', 32)->default('created');
                $table->text('totais_json')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sisfolha_import_stg_rows')) {
            Schema::create('sisfolha_import_stg_rows', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('run_id');
                $table->unsignedInteger('line_number')->default(0);
                $table->string('cpf_norm', 11)->index();
                $table->string('matricula_norm', 64)->index();
                $table->string('nome', 255)->nullable();
                $table->string('setor_codigo_externo', 120)->nullable()->index();
                $table->string('cargo_codigo_sisfolha', 120)->nullable()->index();
                $table->string('pis_norm', 32)->nullable();
                $table->longText('payload_json')->nullable();
                $table->string('status', 24)->default('pending')->index();
                $table->string('motivo', 512)->nullable();
                $table->unsignedBigInteger('promoted_pessoa_id')->nullable();
                $table->unsignedBigInteger('promoted_usuario_id')->nullable();
                $table->unsignedBigInteger('promoted_funcionario_id')->nullable();
                $table->unsignedBigInteger('promoted_lotacao_id')->nullable();
                $table->timestamps();

                $table->index(['run_id', 'status']);
                $table->index(['run_id', 'matricula_norm']);
            });
        }

        if (! Schema::hasTable('sisfolha_cargo_depara')) {
            Schema::create('sisfolha_cargo_depara', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('codigo_sisfolha', 64)->unique();
                $table->unsignedInteger('cargo_id')->nullable()->index();
                $table->string('pccv_sigla', 32)->nullable();
                $table->boolean('ativo')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sisfolha_import_stg_rows');
        Schema::dropIfExists('sisfolha_import_runs');
        Schema::dropIfExists('sisfolha_cargo_depara');
    }
};
