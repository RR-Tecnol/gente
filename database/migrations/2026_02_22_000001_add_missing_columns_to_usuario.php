<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona colunas que faltaram na migration inicial de USUARIO.
 * Segura: usa Schema::hasColumn para não duplicar em produção onde já existem.
 */
class AddMissingColumnsToUsuario extends Migration
{
    public function up()
    {
        // USUARIO_ULTIMO_ACESSO — salvo pelo LoginController após cada login bem-sucedido
        if (Schema::hasTable('USUARIO') && !Schema::hasColumn('USUARIO', 'USUARIO_ULTIMO_ACESSO')) {
            Schema::table('USUARIO', function (Blueprint $table) {
                $table->dateTime('USUARIO_ULTIMO_ACESSO')->nullable()->after('USUARIO_ATIVO');
            });
        }

        // USUARIO_VIGENCIA — LoginController verifica se usuário está dentro da vigência
        if (Schema::hasTable('USUARIO') && !Schema::hasColumn('USUARIO', 'USUARIO_VIGENCIA')) {
            Schema::table('USUARIO', function (Blueprint $table) {
                $table->date('USUARIO_VIGENCIA')->nullable()->after('USUARIO_ULTIMO_ACESSO');
            });
        }

        // USUARIO_PRIMEIRO_ACESSO — flag para forçar troca de senha no primeiro login
        if (Schema::hasTable('USUARIO') && !Schema::hasColumn('USUARIO', 'USUARIO_PRIMEIRO_ACESSO')) {
            Schema::table('USUARIO', function (Blueprint $table) {
                $table->integer('USUARIO_PRIMEIRO_ACESSO')->default(1)->after('USUARIO_VIGENCIA');
            });
        }

        // USUARIO_ALTERAR_SENHA — flag para forçar troca de senha
        if (Schema::hasTable('USUARIO') && !Schema::hasColumn('USUARIO', 'USUARIO_ALTERAR_SENHA')) {
            Schema::table('USUARIO', function (Blueprint $table) {
                $table->integer('USUARIO_ALTERAR_SENHA')->default(0)->after('USUARIO_PRIMEIRO_ACESSO');
            });
        }

        // USUARIO_CPF / USUARIO_EMAIL — usados em outras partes do sistema
        if (Schema::hasTable('USUARIO') && !Schema::hasColumn('USUARIO', 'USUARIO_EMAIL')) {
            Schema::table('USUARIO', function (Blueprint $table) {
                $table->string('USUARIO_EMAIL', 200)->nullable()->after('USUARIO_ALTERAR_SENHA');
            });
        }
    }

    public function down()
    {
        // No-op intencional — não removemos colunas em produção
    }
}
