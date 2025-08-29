<?php

// AppointmentService.php (complete implementation)
namespace App\Services;

use App\Models\TimeSlot;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AppointmentService
{
    public function bookAppointment($patientId, $slotId, $reason, $paymentMethod)
    {
        return DB::transaction(function () use ($patientId, $slotId, $reason, $paymentMethod) {
            $slot = TimeSlot::with('doctor')->findOrFail($slotId);

            $slot->update(['is_booked' => true]);

            return Appointment::create([
                'patient_id' => $patientId,
                'doctor_id' => $slot->doctor_id,
                'time_slot_id' => $slotId,
                'appointment_date' => $slot->date,
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'reason' => $reason,
                'fee' => $slot->doctor->consultation_fee,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentMethod === 'insurance' ? 'covered' : 'pending',
                'status' => 'booked'
            ]);
        });
    }

    public function generateTimeSlots($doctorId, $startDate, $weeks = 2)
    {
   }
}
