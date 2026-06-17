<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('GENTE_ASSIGNMENT')) {
            return;
        }

        Schema::create('GENTE_ASSIGNMENT', function (Blueprint $table) {
            $table->increments('GENTE_ASSIGNMENT_ID');
            $table->integer('USUARIO_ID');
            $table->unsignedInteger('GENTE_ROLE_ID');
            $table->string('TENANT_TYPE', 32);
            $table->integer('TENANT_ID');
            $table->date('VIGENCIA_INICIO');
            $table->date('VIGENCIA_FIM')->nullable();
            $table->boolean('ASSIGNMENT_ATIVO')->default(true);
            $table->string('ORIGEM', 32);
            $table->text('METADADOS_JSON')->nullable();
            $table->timestamps();

            $table->index(['USUARIO_ID', 'ASSIGNMENT_ATIVO'], 'IX_GENTE_ASN_USUARIO');
            $table->index(['TENANT_TYPE', 'TENANT_ID', 'ASSIGNMENT_ATIVO'], 'IX_GENTE_ASN_TENANT');
            $table->index(['GENTE_ROLE_ID', 'ASSIGNMENT_ATIVO'], 'IX_GENTE_ASN_ROLE');
            $table->index(['USUARIO_ID', 'TENANT_TYPE', 'TENANT_ID', 'ASSIGNMENT_ATIVO'], 'IX_GENTE_ASN_USR_TENANT');
        });

        if (Schema::hasTable('GENTE_ROLE')) {
            Schema::table('GENTE_ASSIGNMENT', function (Blueprint $table) {
                $table->foreign('GENTE_ROLE_ID', 'FK_GENTE_ASN_ROLE')
                    ->references('GENTE_ROLE_ID')
                    ->on('GENTE_ROLE')
                    ->onDelete('no action');
            });
        }

        if (Schema::hasTable('USUARIO')) {
            Schema::table('GENTE_ASSIGNMENT', function (Blueprint $table) {
                $table->foreign('USUARIO_ID', 'FK_GENTE_ASN_USUARIO')
                    ->references('USUARIO_ID')
                    ->on('USUARIO')
                    ->onDelete('no action');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('GENTE_ASSIGNMENT');
    }
};
