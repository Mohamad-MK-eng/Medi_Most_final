<?php
// app/Notifications/DoctorAppointmentCancelled.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Appointment;

class DoctorAppointmentCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public $appointment;

    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Appointment Cancellation Notification')
            ->line('An appointment has been cancelled by the patient.')
            ->line('Patient: ' . $this->appointment->patient->user->full_name)
            ->line('Original Date: ' . $this->appointment->appointment_date->format('M j, Y g:i A'))
            ->line('Reason: ' . ($this->appointment->cancellation_reason ?? 'Not specified'))
            ->line('Clinic: ' . $this->appointment->clinic->name)
            ->action('View Appointments', url('/doctor/appointments'))
            ->line('Thank you for your service!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'appointment_id' => $this->appointment->id,
            'patient_name' => $this->appointment->patient->user->full_name,
            'original_date' => $this->appointment->appointment_date->format('Y-m-d H:i:s'),
            'reason' => $this->appointment->cancellation_reason ?? 'Not specified',
            'message' => 'An appointment has been cancelled by the patient',
            'url' => '/doctor/appointments'
        ];
    }
}
