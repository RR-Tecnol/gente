<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('calendar_overrides')) {
            Schema::create('calendar_overrides', function (Blueprint $table) {
                $table->increments('id');
                $table->string('title', 180);
                $table->date('date');
                $table->enum('type', ['holiday', 'sector_off', 'individual_off'])->default('holiday');
                $table->enum('scope', ['global', 'sector', 'user'])->default('global');
                $table->integer('target_id')->nullable();
                $table->decimal('pay_multiplier', 5, 2)->default(2.00);
                $table->boolean('is_point_facultative')->default(false);
                $table->boolean('impacts_bank_of_hours')->default(true);
                $table->text('note')->nullable();
                $table->integer('created_by')->nullable();
                $table->timestamps();

                $table->index(['date', 'scope']);
                $table->index(['scope', 'target_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_overrides');
    }
};
