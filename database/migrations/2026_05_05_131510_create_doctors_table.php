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
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->string('personal_image');
            $table->string('document_image');
            $table->enum('doctor_specialization',['Cardiology' ,'Ophthalmology','Dentistry','Pulmonology','Pediatrics','Gastroenterology','Neurology','General Surgery', 'Cosmetic Surgery']);
            $table->json('working_days');
            $table->text('bio');
            $table->integer('years_of_experience');
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
