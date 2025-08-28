<?php

// app/Notifications/AppointmentCancelled.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Appointment;

class AppointmentCancelled extends Notification implements ShouldQueue
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

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Appointment Cancellation Confirmation')
            ->line('Your appointment has been successfully cancelled.')
            ->line('Doctor: ' . $this->appointment->doctor->user->full_name)
            ->line('Original Date: ' . $this->appointment->appointment_date->format('M j, Y g:i A'))
            ->line('Reason: ' . ($this->appointment->cancellation_reason ?? 'Not specified'))
            ->line('Clinic: ' . $this->appointment->clinic->name)
            ->action('View Appointments', url('/appointments/'))
            ->line('Thank you for using our service!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'appointment_id' => $this->appointment->id,
            'doctor_name' => $this->appointment->doctor->user->full_name,
            'original_date' => $this->appointment->appointment_date->format('Y-m-d H:i:s'),
            'reason' => $this->appointment->cancellation_reason ?? 'Not specified',
            'message' => 'Your appointment has been cancelled',
            'url' => '/appointments/'
        ];
    }
}
