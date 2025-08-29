<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class TimeSlot extends Model
{
    protected $fillable = [
        'doctor_id',
        'date',
        'start_time',
        'end_time',
        'is_booked'
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'start_time' => 'string',
        'end_time' => 'string',
        'is_booked' => 'boolean'
    ];



    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public static function cleanupOldSlots()
    {
        $now = Carbon::now();
        return self::where('date', '<', $now->format('Y-m-d'))
            ->orWhere(function ($query) use ($now) {
                $query->where('date', $now->format('Y-m-d'))
                    ->where('end_time', '<', $now->format('H:i:s'));
            })
            ->delete();
    }


    public function getFormattedStartTimeAttribute(): string
    {
        return Carbon::parse($this->start_time)->format('g:i A');
    }


    public function getStartTimeDigitalAttribute(): string
    {
        return Carbon::parse($this->start_time)->format('H:i');
    }

    public function getEndTimeDigitalAttribute(): string
    {
        return Carbon::parse($this->end_time)->format('H:i');
    }





    public function getFormattedEndTimeAttribute(): string
    {
        return Carbon::parse($this->end_time)->format('g:i A');
    }
}
