<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ANEXO_AFASTAMENTO')) {
            Schema::create('ANEXO_AFASTAMENTO', function (Blueprint $table) {
                $table->increments('ANEXO_AFASTAMENTO_ID');
                $table->integer('AFASTAMENTO_ID');
                $table->longText('ANEXO_AFASTAMENTO_ARQUIVO');
                $table->string('ANEXO_AFASTAMENTO_DESCRICAO', 255)->nullable();
                $table->string('ANEXO_AFASTAMENTO_NOME', 255)->nullable();
                $table->string('ANEXO_AFASTAMENTO_EXTENSAO', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ANEXO_AFASTAMENTO')) {
            Schema::drop('ANEXO_AFASTAMENTO');
        }
    }
};
