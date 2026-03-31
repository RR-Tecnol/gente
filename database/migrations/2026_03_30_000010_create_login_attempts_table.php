<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('LOGIN_ATTEMPTS', function (Blueprint $table) {
            $table->id();
            $table->string('IP', 45);              // IPv4 ou IPv6
            $table->string('LOGIN', 100)->nullable();
            $table->boolean('SUCESSO')->default(false);
            $table->timestamp('TENTATIVA_EM')->useCurrent();
            $table->index(['IP', 'TENTATIVA_EM']);
            $table->index(['LOGIN', 'TENTATIVA_EM']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('LOGIN_ATTEMPTS');
    }
};
