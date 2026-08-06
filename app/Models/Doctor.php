<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shift_id',
        'personal_image',
        'document_image',
        'doctor_specialization',
        'working_days',
        'bio',
        'years_of_experience',
        'phone_number',
    ];

    protected $casts = [
        'working_days' => 'array',
    ];

    /**
     * علاقة الطبيب مع حساب المستخدم (User)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * علاقة الطبيب مع المناوبة (Shift)
     */
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * علاقة الطبيب مع المواعيد (Appointments)
     */
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * علاقة الطبيب مع المرضى الذين أضافوه للمفضلة
     */
    public function favoritedByPatients()
    {
        return $this->belongsToMany(User::class, 'favorites');
    }

    /**
     * علاقة الطبيب مع التقييمات (Feedbacks)
     */
    public function feedbacks()
    {
        return $this->hasMany(Feedback::class);
    }

    /**
     * علاقة الطبيب مع السجلات الطبية (Medical Records)
     */
    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class);
    }
}
