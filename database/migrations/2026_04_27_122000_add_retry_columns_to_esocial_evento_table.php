<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ESOCIAL_EVENTO')) {
            return;
        }

        Schema::table('ESOCIAL_EVENTO', function (Blueprint $table) {
            if (!Schema::hasColumn('ESOCIAL_EVENTO', 'RETRY_COUNT')) {
                $table->integer('RETRY_COUNT')->default(0);
            }
            if (!Schema::hasColumn('ESOCIAL_EVENTO', 'NEXT_RETRY_AT')) {
                $table->dateTime('NEXT_RETRY_AT')->nullable();
            }
            if (!Schema::hasColumn('ESOCIAL_EVENTO', 'LAST_ERROR')) {
                $table->text('LAST_ERROR')->nullable();
            }
            if (!Schema::hasColumn('ESOCIAL_EVENTO', 'IDEMPOTENCY_KEY')) {
                $table->string('IDEMPOTENCY_KEY', 80)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ESOCIAL_EVENTO')) {
            return;
        }
        Schema::table('ESOCIAL_EVENTO', function (Blueprint $table) {
            foreach (['RETRY_COUNT', 'NEXT_RETRY_AT', 'LAST_ERROR', 'IDEMPOTENCY_KEY'] as $col) {
                if (Schema::hasColumn('ESOCIAL_EVENTO', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
