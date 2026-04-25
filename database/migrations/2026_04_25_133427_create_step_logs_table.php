<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('step_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('run_id');
            $table->string('step_id');
            $table->string('step_name');
            $table->string('status'); // pending, running, success, failed, retrying
            $table->integer('attempt')->default(1);
            $table->text('output')->nullable();
            $table->text('error')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('run_id')->references('id')->on('workflow_runs');
            $table->index(['run_id', 'step_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('step_logs');
    }
};
