<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{

    protected $fillable = ['user_id', 'personal_image', 'document_image', 'doctor_specialization', 'working_days', 'bio', 'years_of_experience'];
    protected $casts = [
        'working_days' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function favoritedByPatients()
    {
        return $this->belongsToMany(User::class,'favorites');
    }
}
