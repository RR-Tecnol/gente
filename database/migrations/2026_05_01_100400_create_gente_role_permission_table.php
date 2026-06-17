<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('GENTE_ROLE_PERMISSION')) {
            return;
        }

        Schema::create('GENTE_ROLE_PERMISSION', function (Blueprint $table) {
            $table->increments('GENTE_ROLE_PERMISSION_ID');
            $table->unsignedInteger('GENTE_ROLE_ID');
            $table->unsignedInteger('GENTE_PERMISSION_ID');
            $table->timestamps();

            $table->unique(['GENTE_ROLE_ID', 'GENTE_PERMISSION_ID'], 'UQ_GENTE_ROLE_PERM');
            $table->index('GENTE_PERMISSION_ID', 'IX_GENTE_ROLE_PERM_PERMISSION');
        });

        if (Schema::hasTable('GENTE_ROLE') && Schema::hasTable('GENTE_PERMISSION')) {
            Schema::table('GENTE_ROLE_PERMISSION', function (Blueprint $table) {
                $table->foreign('GENTE_ROLE_ID', 'FK_GENTE_ROLE_PERM_ROLE')
                    ->references('GENTE_ROLE_ID')
                    ->on('GENTE_ROLE')
                    ->onDelete('no action');
                $table->foreign('GENTE_PERMISSION_ID', 'FK_GENTE_ROLE_PERM_PERM')
                    ->references('GENTE_PERMISSION_ID')
                    ->on('GENTE_PERMISSION')
                    ->onDelete('no action');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('GENTE_ROLE_PERMISSION');
    }
};
