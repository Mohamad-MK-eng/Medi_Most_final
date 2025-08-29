<?php

// app/Notifications/AppointmentBooked.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Appointment;

class AppointmentBooked extends Notification implements ShouldQueue
{
    use Queueable;

    public $appointment;
    public $aftercommit = true;


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
            ->subject('Appointment Confirmation')
            ->line('Your appointment has been successfully booked.')
            ->line('Doctor: ' . $this->appointment->doctor->user->full_name)
            ->line('Date: ' . $this->appointment->appointment_date->format('M j, Y g:i A'))
            ->line('Clinic: ' . $this->appointment->clinic->name)
            ->action('View Appointment', url('/appointments/' . $this->appointment->id))
            ->line('Thank you for using our service!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'appointment_id' => $this->appointment->id,
            'doctor_name' => $this->appointment->doctor->user->full_name,
            'appointment_date' => $this->appointment->appointment_date->format('Y-m-d H:i:s'),
            'message' => 'Your appointment has been booked successfully',
            'url' => '/appointments/' . $this->appointment->id
        ];
    }
}
