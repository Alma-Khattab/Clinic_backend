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

        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('set null');
            $table->string('personal_image')->nullable();
            $table->string('document_image')->nullable();
            $table->enum('doctor_specialization',['Cardiology' ,'Ophthalmology','Dentistry','Pulmonology','Pediatrics','Gastroenterology','Neurology','General Surgery', 'Cosmetic Surgery'])->nullable();
            $table->json('working_days')->nullable();
            $table->text('bio')->nullable();
            $table->integer('years_of_experience')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
