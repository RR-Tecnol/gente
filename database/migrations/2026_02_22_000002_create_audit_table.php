<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela AUDIT usada pelo AuditHelper para rastrear operações CRUD.
 * Sem ela, qualquer operação auditada causa 500.
 */
class CreateAuditTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('AUDIT')) {
            Schema::create('AUDIT', function (Blueprint $table) {
                $table->bigInteger('AUDIT_ID')->autoIncrement();
                $table->dateTime('AUDIT_DATA')->nullable();
                $table->integer('AUDIT_USER_ID')->nullable();
                $table->string('AUDIT_USER', 200)->nullable();
                $table->string('AUDIT_TABELA', 100)->nullable();
                $table->integer('AUDIT_LINHA_ID')->nullable();
                $table->string('AUDIT_CAMPO', 100)->nullable();
                $table->text('AUDIT_ANTES')->nullable();
                $table->text('AUDIT_DEPOIS')->nullable();
                $table->string('AUDIT_OPERACAO', 1)->nullable(); // I=Insert, U=Update, D=Delete
            });
        }
    }

    public function down()
    {
        // No-op intencional
    }
}
