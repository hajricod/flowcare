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
         Schema::create('slots', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('branch_id', 50);
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->string('service_type_id', 50);
            $table->foreign('service_type_id')->references('id')->on('service_types')->cascadeOnDelete();
            $table->string('staff_id', 50)->nullable();
            $table->foreign('staff_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('start_at');
            $table->timestampTz('end_at');
            $table->integer('capacity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->softDeletesTz();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slots');
    }
};
