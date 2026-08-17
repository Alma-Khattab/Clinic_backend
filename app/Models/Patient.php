<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $guarded =[];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function appointments(){
        // return $this->hasMany(Appointment::class);
         return $this->hasMany(Appointment::class, 'user_id', 'user_id');
    }

    public function medicalRecord()
{
    return $this->hasMany(MedicalRecord::class, 'patient_id', 'user_id');
}
}
