<?php

namespace App\Models;

use App\Traits\HandlesFiles;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
/**
 *
 *
 * @property int $id
 * @property int $user_id
 * @property string $specialty
 * @property array<array-key, mixed> $workdays
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Appointment> $appointments
 * @property-read int|null $appointments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Prescription> $prescriptions
 * @property-read int|null $prescriptions_count
 * @property-read int|null $services_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Doctor newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Doctor newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Doctor query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Doctor whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Doctor whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Doctor whereSpecialty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Doctor whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Doctor whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Doctor whereWorkdays($value)
 * @mixin \Eloquent
 */
class Doctor extends Model
{
    use HandlesFiles;
    use SoftDeletes;



    protected $fillable = [
        'user_id',
        'clinic_id',
        'specialty',
        'workdays',
        'salary_id',
        'consultation_fee',
        'is_active'
    ];

protected $dates =['deleted_at'];



    protected $casts = [
        'clinic_id',
        'workdays' => 'array',
        'experience_start_date' => 'date:Y-m-d',
        'rating' => 'double',
        'consultation_fee' => 'decimal:2'
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }



    public function prescriptions()
    {
        return $this->hasManyThrough(Prescription::class, Appointment::class)->withTrashed();
    }




    public function clinic()
    {
        return $this->belongsTo(Clinic::class,"clinic_id");
    }

    public function timeSlots()
    {
        return $this->hasMany(TimeSlot::class);
    }
    public function salary()
{
    return $this->belongsTo(Salary::class);
}



    // Helper methods
    public function isAvailable($date, $time)
    {
        return $this->workdays[$date] ?? false;
    }




    public function getGenderAttribute()
{
    return $this->user->gender;
}


    public function getAvailableServices()
    {
        return $this->services()->get();
    }



    // In App\Models\Doctor.php

    public function getAvailableSlots($date)
    {
        $dayOfWeek = strtolower(Carbon::parse($date)->englishDayOfWeek);

        // Check if doctor works this day
        $schedule = $this->schedules()->where('day', $dayOfWeek)->first();
        if (!$schedule) {
            return collect();
        }

        // Generate fixed slots (e.g., 5 per day)
        $slots = [];
        $start = Carbon::parse($schedule->start_time);
        $end = Carbon::parse($schedule->end_time);
        $interval = $start->diffInMinutes($end) / 5; // 5 slots per day

        for ($i = 0; $i < 5; $i++) {
            $slotStart = $start->copy()->addMinutes($i * $interval);
            $slotEnd = $slotStart->copy()->addMinutes($interval);

            // Check if slot is already booked
            $isBooked = Appointment::where('doctor_id', $this->id)
                ->whereDate('appointment_date', $date)
                ->whereTime('appointment_date', '>=', $slotStart->format('H:i:s'))
                ->whereTime('appointment_date', '<', $slotEnd->format('H:i:s'))
                ->exists();

            if (!$isBooked) {
                $slots[] = [
                    'start_time' => $slotStart->format('g:i A'),
                    'end_time' => $slotEnd->format('g:i A')
                ];
            }
        }

        return collect($slots);
    }


    public function reviews()
    {
        return $this->hasMany(Review::class);
    }


public function averageRating()
{
    return $this->reviews()->avg('rating') ?? 0;
}

public function updateRating()
{
    $this->rating = $this->averageRating();
    $this->save();
}



    public function schedules()
    {
        return $this->hasMany(DoctorSchedule::class);
    }











    public function getExperienceYearsAttribute()
    {
        // Get the start date (use experience_start_date if available, otherwise created_at)
        $startDate = $this->experience_start_date ?? $this->created_at;

        // If no start date exists, return 0
        if (!$startDate) {
            return 0;
        }

        // Convert to Carbon instances
        $start = Carbon::parse($startDate);
        $now = Carbon::now();

        // Ensure start date is in the past
        if ($start->isFuture()) {
            return 0;
        }

        // Calculate and return whole years of experience
        return (int)$start->diffInYears($now);
    }



public function scopeTopRated($query, $limit = 5)
{
    return $query->with('user') // Eager load the user relationship
        ->whereHas('reviews') // Only include doctors with reviews
        ->orderBy('rating', 'DESC')
        ->orderBy('experience_years', 'DESC') // Secondary sort by experience
        ->limit($limit);
}

/**
 * Calculate and update the doctor's average rating
 */





}
