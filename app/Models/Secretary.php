<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\SalarySetting;
use App\Traits\HandlesFiles;

/**
 *
 *
 * @property int $id
 * @property int $user_id
 * @property string $workdays
 * @property string|null $emergency_absences
 * @property string|null $performance_metrics
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Appointment> $appointments
 * @property-read int|null $appointments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Salary> $salaries
 * @property-read int|null $salaries_count
 * @property-read SalarySetting|null $salarytsetting
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Secretary newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Secretary newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Secretary query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Secretary whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Secretary whereEmergencyAbsences($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Secretary whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Secretary wherePerformanceMetrics($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Secretary whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Secretary whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Secretary whereWorkdays($value)
 * @mixin \Eloquent
 */
class Secretary extends Model
{
    use HandlesFiles;

    protected $fillable = [
        'user_id',
        'workdays',

        'profile_picture'


    ];


    protected  $casts = [
'workdays' => 'array',
        'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appointments()
    {

        return $this->hasMany(Appointment::class);
    }


    public function payments()
    {
        return $this->hasMany(Payment::class);
    }



    public function salaries()
    {

        return $this->hasMany(Salary::class);
    }


    public function salarytsetting()
    {
        return $this->belongsTo(SalarySetting::class);
    }




    // Helper methods
    public function getPerformanceMetrics()
    {
        return $this->performance_metrics;
    }

    public function getEmergencyAbsences()
    {
        return $this->emergency_absences;
    }
}
