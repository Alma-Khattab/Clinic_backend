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
        Schema::create('medical_records', function (Blueprint $table) {
        $table->id();
        $table->foreignId('patient_id')->constrained('patients', 'user_id')->onDelete('cascade');
        $table->foreignId('doctor_id')->constrained('doctors')->onDelete('cascade');
        $table->foreignId('appointment_id')->constrained('appointments')->onDelete('cascade');
        $table->text('complaint');
        $table->string('blood_pressure')->nullable();
        $table->integer('heart_rate')->nullable();
        $table->decimal('temperature', 4, 1)->nullable();
        $table->text('diagnosis');
        $table->text('prescription')->nullable();
        $table->text('requested_tests')->nullable();
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
