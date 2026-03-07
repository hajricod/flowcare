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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('actor_id', 50)->nullable();
            $table->string('actor_role')->nullable();
            $table->string('action_type');
            $table->string('entity_type');
            $table->string('entity_id');
            $table->jsonb('metadata')->nullable();
            $table->string('branch_id', 50)->nullable();
            $table->timestampTz('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
