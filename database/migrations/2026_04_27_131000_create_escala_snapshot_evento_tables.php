<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ESCALA_SNAPSHOT')) {
            Schema::create('ESCALA_SNAPSHOT', function (Blueprint $table) {
                $table->increments('SNAPSHOT_ID');
                $table->integer('ESCALA_ID');
                $table->integer('VERSAO');
                $table->string('SNAPSHOT_HASH', 128);
                $table->text('PAYLOAD_JSON')->nullable();
                $table->integer('USUARIO_ID')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ESCALA_EVENTO')) {
            Schema::create('ESCALA_EVENTO', function (Blueprint $table) {
                $table->increments('EVENTO_ID');
                $table->integer('ESCALA_ID');
                $table->string('EVENTO_TIPO', 80);
                $table->text('EVENTO_PAYLOAD')->nullable();
                $table->integer('USUARIO_ID')->nullable();
                $table->timestamp('EVENTO_EM')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ESCALA_EVENTO')) {
            Schema::drop('ESCALA_EVENTO');
        }
        if (Schema::hasTable('ESCALA_SNAPSHOT')) {
            Schema::drop('ESCALA_SNAPSHOT');
        }
    }
};
