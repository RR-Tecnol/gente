<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('FUNCIONARIO') && ! Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_HONEYTOKEN')) {
            Schema::table('FUNCIONARIO', function (Blueprint $table) {
                $table->unsignedTinyInteger('FUNCIONARIO_HONEYTOKEN')->default(0);
            });
        }

        if (! Schema::hasTable('GENTE_IP_BLOCKLIST')) {
            Schema::create('GENTE_IP_BLOCKLIST', function (Blueprint $table) {
                $table->increments('GENTE_IP_BLOCKLIST_ID');
                $table->string('IP', 45);
                $table->timestamp('BLOQUEADO_ATE');
                $table->string('MOTIVO', 120)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index('IP', 'idx_gente_ip_blocklist_ip');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('GENTE_IP_BLOCKLIST')) {
            Schema::drop('GENTE_IP_BLOCKLIST');
        }
        if (Schema::hasTable('FUNCIONARIO') && Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_HONEYTOKEN')) {
            Schema::table('FUNCIONARIO', function (Blueprint $table) {
                $table->dropColumn('FUNCIONARIO_HONEYTOKEN');
            });
        }
    }
};
