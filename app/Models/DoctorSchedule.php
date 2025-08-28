<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorSchedule extends Model
{


    protected $fillable = ['doctor_id', 'day', 'start_time', 'end_time'];

    protected $casts = [
        'doctor_id',
        'day' => 'string',
        'start_time' => 'string',
        'end_time' => 'string',

    ];






    public function doctor()
    {

        return $this->belongsTo(Doctor::class);
    }
}
