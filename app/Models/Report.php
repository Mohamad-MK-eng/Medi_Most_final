<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{

protected $fillable = [
        'appointment_id',
        'title',
        'content',
        'notes'
    ];

 public function toArray()
    {
        return [
            'date' => $this->appointment->appointment_date->format('Y-m-d H:i A'),
            'clinic' => $this->appointment->clinic->name,
            'doctor' => $this->appointment->doctor->user->name,
            'specialty' => $this->appointment->doctor->specialty,
            'title' => $this->title,
            'content' => $this->content,
            'prescriptions' => $this->prescriptions->map(function($prescription) {
                return [
                    'medication' => $prescription->medication,
                    'dosage' => $prescription->dosage,
                    'frequency' => $prescription->frequency,
                    'instructions' => $prescription->instructions,
                    'is_completed' => (bool)$prescription->is_completed
                ];
            })
        ];
    }


public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }}
