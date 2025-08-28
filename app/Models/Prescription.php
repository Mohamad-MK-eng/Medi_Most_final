<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int $appointment_id
 * @property string $medication
 * @property string $dosage
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Appointment $appointment
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereAppointmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereDosage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereMedication($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Prescription extends Model
{
  protected $fillable = [
        'report_id',
        'appointment_id',
        'medication',
        'dosage',
        'frequency',
        'instructions',
        'is_completed',
        'issue_date'
    ];

    protected $casts = [
        'is_completed' => 'boolean'
    ];



        public function report()
    {
        return $this->belongsTo(Report::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }


    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }


    public function getMedicationDetails()
    {

        return [
            'medication' => $this->medication,
            'dosage' => $this->dosage,
            'instructions ' => $this->instructions
        ];
    }
}
