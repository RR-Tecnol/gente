<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('AUDIT_LOG')) {
            return;
        }
        if (Schema::hasColumn('AUDIT_LOG', 'HASH_CONCAT')) {
            return;
        }
        $driver = DB::getDriverName();
        Schema::table('AUDIT_LOG', function (Blueprint $table) use ($driver) {
            if ($driver === 'mysql') {
                $table->string('HASH_CONCAT', 64)->nullable()->after('id');
            } else {
                $table->string('HASH_CONCAT', 64)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('AUDIT_LOG') || ! Schema::hasColumn('AUDIT_LOG', 'HASH_CONCAT')) {
            return;
        }
        Schema::table('AUDIT_LOG', function (Blueprint $table) {
            $table->dropColumn('HASH_CONCAT');
        });
    }
};
