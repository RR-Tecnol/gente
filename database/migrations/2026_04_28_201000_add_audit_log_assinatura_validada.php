<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('AUDIT_LOG')) {
            return;
        }
        if (Schema::hasColumn('AUDIT_LOG', 'ASSINATURA_VALIDADA')) {
            return;
        }
        Schema::table('AUDIT_LOG', function (Blueprint $table) {
            $table->boolean('ASSINATURA_VALIDADA')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('AUDIT_LOG') && Schema::hasColumn('AUDIT_LOG', 'ASSINATURA_VALIDADA')) {
            Schema::table('AUDIT_LOG', function (Blueprint $table) {
                $table->dropColumn('ASSINATURA_VALIDADA');
            });
        }
    }
};
