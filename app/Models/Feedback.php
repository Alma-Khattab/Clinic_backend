<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    protected $table = 'feedbacks'; // تحديد اسم الجدول لتجنب مشاكل الجمع بالإنجليزية

    protected $fillable = [
        'appointment_id',
        'user_id',
        'doctor_id',
        'comment',
        'is_anonymous'
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
}
