<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'doctor_id',
        'patient_id',
        'appointment_date',
        'appointment_time',
        'status',
        'reminder_sent',
    ];

    /**
     * علاقة الموعد مع حساب المستخدم (User)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * علاقة الموعد مع الطبيب (Doctor)
     */
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * علاقة الموعد مع المريض (Patient)
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * علاقة الموعد مع التقييمات (Feedbacks)
     */
    public function feedbacks()
    {
        return $this->hasMany(Feedback::class);
    }

    /**
     * علاقة الموعد مع السجل الطبي (MedicalRecord)
     */
    public function medicalRecord()
    {
        return $this->hasOne(MedicalRecord::class, 'appointment_id');
    }
}
