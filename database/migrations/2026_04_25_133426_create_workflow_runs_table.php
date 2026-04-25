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
        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workflow_id');
            $table->uuid('tenant_id');
            $table->integer('version');
            $table->string('status'); // pending, running, success, failed, timeout
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('trigger_context')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at']); // composite index untuk health panel
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_runs');
    }
};
