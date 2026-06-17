<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('SHADOW_RUN')) {
            Schema::create('SHADOW_RUN', function (Blueprint $table) {
                $table->increments('SHADOW_RUN_ID');
                $table->string('RUN_ID', 80)->unique();
                $table->string('COMPETENCIA', 7);
                $table->string('SNAPSHOT_DIR', 255);
                $table->string('SNAPSHOT_SHA256', 64)->nullable();
                $table->string('STATUS', 30)->default('criado');
                $table->integer('TOTAL_SERVIDORES')->default(0);
                $table->integer('TOTAL_ETL_OK')->default(0);
                $table->integer('TOTAL_CALC_OK')->default(0);
                $table->integer('TOTAL_DIFF_OK')->default(0);
                $table->integer('TOTAL_DIFF_CRITICO')->default(0);
                $table->string('OBS', 255)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('SHADOW_CHECKPOINT')) {
            Schema::create('SHADOW_CHECKPOINT', function (Blueprint $table) {
                $table->increments('SHADOW_CHECKPOINT_ID');
                $table->string('RUN_ID', 80);
                $table->string('COMPETENCIA', 7);
                $table->string('CPF', 14)->nullable();
                $table->string('SERVIDOR_KEY', 120)->nullable();
                $table->string('ETAPA', 20); // etl_ok|calc_ok|diff_ok
                $table->string('STATUS', 20)->default('ok');
                $table->string('IDEMPOTENCY_KEY', 140);
                $table->string('PAYLOAD_HASH', 64)->nullable();
                $table->text('DETALHE')->nullable();
                $table->timestamps();
                $table->unique(['RUN_ID', 'IDEMPOTENCY_KEY', 'ETAPA'], 'ux_shadow_run_idem_etapa');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('SHADOW_CHECKPOINT')) {
            Schema::drop('SHADOW_CHECKPOINT');
        }
        if (Schema::hasTable('SHADOW_RUN')) {
            Schema::drop('SHADOW_RUN');
        }
    }
};

