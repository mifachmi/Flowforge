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
        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workflow_id');
            $table->integer('version');
            $table->json('dag_definition'); // simpan DAG sebagai JSON
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->foreign('workflow_id')->references('id')->on('workflow_definitions');
            $table->unique(['workflow_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_versions');
    }
};
