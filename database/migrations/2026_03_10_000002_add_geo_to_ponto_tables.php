<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Geolocalização no Terminal de Ponto ──────────────────────────
        Schema::table('TERMINAL_PONTO', function (Blueprint $table) {
            if (!Schema::hasColumn('TERMINAL_PONTO', 'TERMINAL_LATITUDE')) {
                $table->decimal('TERMINAL_LATITUDE', 10, 8)->nullable()->after('TERMINAL_ATIVO');
            }
            if (!Schema::hasColumn('TERMINAL_PONTO', 'TERMINAL_LONGITUDE')) {
                $table->decimal('TERMINAL_LONGITUDE', 11, 8)->nullable()->after('TERMINAL_LATITUDE');
            }
            if (!Schema::hasColumn('TERMINAL_PONTO', 'TERMINAL_RAIO_METROS')) {
                $table->unsignedInteger('TERMINAL_RAIO_METROS')->default(50)->after('TERMINAL_LONGITUDE');
            }
        });

        // ── GPS + Facial no Registro de Ponto ────────────────────────────
        Schema::table('REGISTRO_PONTO', function (Blueprint $table) {
            if (!Schema::hasColumn('REGISTRO_PONTO', 'REGISTRO_LATITUDE')) {
                $table->decimal('REGISTRO_LATITUDE', 10, 8)->nullable()->after('REGISTRO_OBSERVACAO');
            }
            if (!Schema::hasColumn('REGISTRO_PONTO', 'REGISTRO_LONGITUDE')) {
                $table->decimal('REGISTRO_LONGITUDE', 11, 8)->nullable()->after('REGISTRO_LATITUDE');
            }
            if (!Schema::hasColumn('REGISTRO_PONTO', 'REGISTRO_DISTANCIA_M')) {
                $table->unsignedInteger('REGISTRO_DISTANCIA_M')->nullable()->after('REGISTRO_LONGITUDE');
            }
            if (!Schema::hasColumn('REGISTRO_PONTO', 'REGISTRO_FACE_OK')) {
                $table->boolean('REGISTRO_FACE_OK')->default(false)->after('REGISTRO_DISTANCIA_M');
            }
            if (!Schema::hasColumn('REGISTRO_PONTO', 'REGISTRO_FOTO_PATH')) {
                $table->string('REGISTRO_FOTO_PATH', 255)->nullable()->after('REGISTRO_FACE_OK');
            }
        });
    }

    public function down(): void
    {
        Schema::table('TERMINAL_PONTO', function (Blueprint $table) {
            $table->dropColumn(['TERMINAL_LATITUDE', 'TERMINAL_LONGITUDE', 'TERMINAL_RAIO_METROS']);
        });
        Schema::table('REGISTRO_PONTO', function (Blueprint $table) {
            $table->dropColumn([
                'REGISTRO_LATITUDE',
                'REGISTRO_LONGITUDE',
                'REGISTRO_DISTANCIA_M',
                'REGISTRO_FACE_OK',
                'REGISTRO_FOTO_PATH',
            ]);
        });
    }
};
