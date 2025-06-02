<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class University extends Model
{
    protected $fillable = ['name', 'slug', 'website', 'city'];

    public function courses()
    {
        return $this->hasMany(Course::class);
    }
}
