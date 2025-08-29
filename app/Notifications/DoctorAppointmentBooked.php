<?php
// app/Notifications/DoctorAppointmentBooked.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Appointment;

class DoctorAppointmentBooked extends Notification implements ShouldQueue
{
    use Queueable;

    public $appointment;

    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'appointment_id' => $this->appointment->id,
            'patient_name' => $this->appointment->patient->user->full_name,
            'appointment_date' => $this->appointment->appointment_date->format('Y-m-d H:i:s'),
            'message' => 'You have a new appointment',
            'url' => '/doctor/appointments/' . $this->appointment->id
        ];
    }
}
