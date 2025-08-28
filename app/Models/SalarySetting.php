<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property string $payment_type
 * @property string|null $base_salary
 * @property string|null $per_patient_bonus
 * @property string|null $per_appointment_bonus
 * @property string|null $performance_thresholds
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Salary> $salaries
 * @property-read int|null $salaries_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Secretary> $secretaries
 * @property-read int|null $secretaries_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalarySetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalarySetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalarySetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalarySetting whereBaseSalary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalarySetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalarySetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalarySetting wherePaymentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalarySetting wherePerAppointmentBonus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalarySetting wherePerPatientBonus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalarySetting wherePerformanceThresholds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalarySetting whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class SalarySetting extends Model
{
    protected $fillable = [
        'payment_type',
        'base_salary',
        'per_patient_bonus',
        'per_appointment_bonus',
        'performance_thresholds'

    ];


    protected $casts = [

        'per_patient_bonus'

    ];
    public function salaries()
    {

        return $this->hasMany(Salary::class);
    }



    public function secretaries()
    {
        return $this->hasMany(Secretary::class);
    }
}
