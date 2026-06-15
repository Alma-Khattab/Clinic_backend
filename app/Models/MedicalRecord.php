<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
    protected $guarded = [];

    public function patient() {
        return $this->belongsTo(Patient::class, 'patient_id', 'user_id');
    }

    public function doctor() {
        return $this->belongsTo(Doctor::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }
}
