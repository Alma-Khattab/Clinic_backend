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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->unique();
            $table->string('personal_image')->nullable();
            $table->string('address');
            $table->string('blood_type');
            $table->text('drug_allergies')->nullable();
            $table->text('chronic_diseases')->nullable();
            $table->text('previous_operations')->nullable();
            $table->text('current_medicines')->nullable();
            $table->unsignedSmallInteger('height');
            $table->float('weight')->unsigned();
            $table->string('job');
            $table->boolean('smoker')->default(false);
            $table->string('marital_status');
            $table->unsignedInteger('missed_appointments_count')->default(0);
            $table->unsignedInteger('rewards_count')->default(0);//معدل
            $table->boolean('has_free_visit')->default(false);//معدل
            $table->softDeletes(); // تفعيل الحذف الناعم للأمان الطبي
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
