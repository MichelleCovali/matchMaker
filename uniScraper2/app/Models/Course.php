<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = [
        'university_id', 
        'title', 
        'type', 
        'location', 
        'url',
        'duration',
        'tuition_fee',
        'start_date',
        'description'
    ];

    public function university()
    {
        return $this->belongsTo(University::class);
    }
}
