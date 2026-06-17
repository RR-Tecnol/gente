<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('FERIADO_GESTAO')) {
            Schema::create('FERIADO_GESTAO', function (Blueprint $table) {
                $table->increments('FERIADO_GESTAO_ID');
                $table->string('HOLIDAY_NAME', 180);
                $table->date('HOLIDAY_DATE');
                $table->string('HOLIDAY_SCOPE', 24)->default('hospital');
                $table->boolean('IS_POINT_FACULTATIVE')->default(false);
                $table->boolean('IMPACTS_BANK_OF_HOURS')->default(true);
                $table->integer('OVERRIDE_TARGET_ID')->nullable();
                $table->string('OVERRIDE_TARGET_TYPE', 24)->nullable(); // hospital|sector|employee
                $table->text('HOLIDAY_NOTE')->nullable();
                $table->integer('CREATED_BY')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('FERIADO_GESTAO')) {
            Schema::dropIfExists('FERIADO_GESTAO');
        }
    }
};
