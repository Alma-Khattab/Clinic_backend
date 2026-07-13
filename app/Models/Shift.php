<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $guarded =[];

    public function doctors()
    {
        return $this->hasMany(Doctor::class);
    }
}
