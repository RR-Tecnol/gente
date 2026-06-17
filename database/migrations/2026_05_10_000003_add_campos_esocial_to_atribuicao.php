<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ATRIBUICAO', function (Blueprint $table) {
            if (!Schema::hasColumn('ATRIBUICAO', 'ATRIBUICAO_CBO')) {
                $table->string('ATRIBUICAO_CBO', 6)->nullable()
                    ->comment('CBO 6 digitos — obrigatorio eSocial S-1040');
            }
            if (!Schema::hasColumn('ATRIBUICAO', 'ATRIBUICAO_COMISSAO')) {
                $table->integer('ATRIBUICAO_COMISSAO')->nullable()
                    ->comment('Tipo eSocial: 1=Funcao Confianca, 2=Cargo Comissao');
            }
            if (!Schema::hasColumn('ATRIBUICAO', 'ATRIBUICAO_GRATIFICACAO')) {
                $table->decimal('ATRIBUICAO_GRATIFICACAO', 15, 2)->nullable()
                    ->comment('Valor da gratificacao em R$');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ATRIBUICAO', function (Blueprint $table) {
            if (Schema::hasColumn('ATRIBUICAO', 'ATRIBUICAO_CBO'))
                $table->dropColumn('ATRIBUICAO_CBO');
            if (Schema::hasColumn('ATRIBUICAO', 'ATRIBUICAO_COMISSAO'))
                $table->dropColumn('ATRIBUICAO_COMISSAO');
            if (Schema::hasColumn('ATRIBUICAO', 'ATRIBUICAO_GRATIFICACAO'))
                $table->dropColumn('ATRIBUICAO_GRATIFICACAO');
        });
    }
};
