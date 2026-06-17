<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('DOCUMENTOS_SERVIDOR')) {
            return;
        }
        if (!Schema::hasColumn('DOCUMENTOS_SERVIDOR', 'HASH_AUTENTICIDADE')) {
            Schema::table('DOCUMENTOS_SERVIDOR', function (Blueprint $table) {
                $table->string('HASH_AUTENTICIDADE', 128)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('DOCUMENTOS_SERVIDOR')) {
            return;
        }
        if (Schema::hasColumn('DOCUMENTOS_SERVIDOR', 'HASH_AUTENTICIDADE')) {
            Schema::table('DOCUMENTOS_SERVIDOR', function (Blueprint $table) {
                $table->dropColumn('HASH_AUTENTICIDADE');
            });
        }
    }
};

