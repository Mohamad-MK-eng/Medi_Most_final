<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Appointment;

class AppointmentRescheduled extends Notification implements ShouldQueue
{
    use Queueable;

    public $appointment;
    public $originalDate;

    public function __construct(Appointment $appointment, $originalDate)
    {
        $this->appointment = $appointment;
        $this->originalDate = $originalDate;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Appointment Rescheduled')
            ->line('An appointment has been rescheduled.')
            ->line($notifiable->isDoctor() ?
                'Patient: ' . $this->appointment->patient->user->full_name :
                'Doctor: ' . $this->appointment->doctor->user->full_name)
            ->line('Original Date: ' . $this->originalDate->format('M j, Y g:i A'))
            ->line('New Date: ' . $this->appointment->appointment_date->format('M j, Y g:i A'))
            ->line('Reason: ' . ($this->appointment->reschedule_reason ?? 'Not specified'))
            ->action('View Appointment', url($notifiable->isDoctor() ?
                '/doctor/appointments/' . $this->appointment->id :
                '/appointments/' . $this->appointment->id))
            ->line('Thank you for using our service!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'appointment_id' => $this->appointment->id,
            'user_name' => $notifiable->isDoctor() ?
                $this->appointment->patient->user->full_name :
                $this->appointment->doctor->user->full_name,
            'original_date' => $this->originalDate->format('Y-m-d H:i:s'),
            'new_date' => $this->appointment->appointment_date->format('Y-m-d H:i:s'),
            'reason' => $this->appointment->reschedule_reason ?? 'Not specified',
            'message' => 'An appointment has been rescheduled',
            'url' => $notifiable->isDoctor() ?
                '/doctor/appointments/' . $this->appointment->id :
                '/appointments/' . $this->appointment->id
        ];
    }
}
