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
        Schema::create('staff_service_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('staff_id', 50);
            $table->string('service_type_id', 50);
            $table->foreign('staff_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('service_type_id')->references('id')->on('service_types')->cascadeOnDelete();
            $table->unique(['staff_id', 'service_type_id']);
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_service_types');
    }
};
