<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $fillable = ['user_id','personal_image'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
