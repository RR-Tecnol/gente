<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('PESSOA', function (Blueprint $table) {
            $table->string('PESSOA_NOME_SOCIAL', 200)->nullable()->after('PESSOA_NOME');
        });
    }

    public function down(): void
    {
        Schema::table('PESSOA', function (Blueprint $table) {
            $table->dropColumn('PESSOA_NOME_SOCIAL');
        });
    }
};
