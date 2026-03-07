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
        Schema::create('appointments', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('customer_id', 50);
            $table->foreign('customer_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('branch_id', 50);
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->string('service_type_id', 50);
            $table->foreign('service_type_id')->references('id')->on('service_types')->cascadeOnDelete();
            $table->string('slot_id', 50)->nullable();
            $table->foreign('slot_id')->references('id')->on('slots')->nullOnDelete();
            $table->string('staff_id', 50)->nullable();
            $table->foreign('staff_id')->references('id')->on('users')->nullOnDelete();
            $table->enum('status', ['BOOKED', 'CHECKED_IN', 'COMPLETED', 'CANCELLED', 'NO_SHOW'])->default('BOOKED');
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->integer('queue_number')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
