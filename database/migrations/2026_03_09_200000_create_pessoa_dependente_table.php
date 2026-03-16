<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('PESSOA_DEPENDENTE')) {
            Schema::create('PESSOA_DEPENDENTE', function (Blueprint $table) {
                $table->bigIncrements('PESSOA_DEPENDENTE_ID');
                $table->unsignedBigInteger('FUNCIONARIO_ID')->index();
                $table->string('PESSOA_DEPENDENTE_NOME', 200);
                $table->string('PESSOA_DEPENDENTE_CPF', 14)->nullable();
                $table->date('PESSOA_DEPENDENTE_NASCIMENTO')->nullable();
                $table->string('PESSOA_DEPENDENTE_PARENTESCO', 10)->nullable(); // código eSocial
                $table->tinyInteger('PESSOA_DEPENDENTE_DEDUCAO_IRRF')->default(1); // 0=sem, 1=dependente, 2=pensão
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('PESSOA_DEPENDENTE');
    }
};
