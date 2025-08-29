<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class EmergencyAppointmentCancellationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $appointment;
    public $doctorName;
    public $reason;
    public $isEmergency;
    public $cancellationDate;

    public function __construct($appointment, $doctorName, $reason, $isEmergency)
    {
        $this->appointment = $appointment;
        $this->doctorName = $doctorName;
        $this->reason = $reason;
        $this->isEmergency = $isEmergency;
        $this->cancellationDate = now();
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        // Load the time slot relationship if not already loaded
        if (!$this->appointment->relationLoaded('timeSlot')) {
            $this->appointment->load('timeSlot');
        }

        $mailMessage = (new MailMessage)
            ->subject($this->isEmergency ? '🚨 Emergency Appointment Cancellation' : 'Appointment Cancellation')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('We regret to inform you that your appointment has been cancelled.')
            ->line('**Appointment Details:**')
            ->line('- Doctor: Dr. ' . $this->doctorName)
            ->line('- Clinic: ' . $this->appointment->clinic->name)
            ->line('- Date: ' . $this->appointment->appointment_date->format('M j, Y'));

        // Safely access time slot properties
        if ($this->appointment->timeSlot) {
            $mailMessage->line('- Time: ' . $this->appointment->timeSlot->start_time . ' - ' . $this->appointment->timeSlot->end_time);
        }

        $mailMessage->line('- Reason: ' . $this->reason);

        if ($this->isEmergency) {
            $mailMessage->line('**⚠️ This is an emergency cancellation.**')
                ->line('We apologize for any inconvenience this may cause.');
        }

        $mailMessage->action('Reschedule Appointment', url('/appointments'))
            ->line('Please contact us if you have any questions or need assistance rescheduling.');

        return $mailMessage;
    }

    public function toDatabase($notifiable)
    {
        // Load the time slot relationship if not already loaded
        if (!$this->appointment->relationLoaded('timeSlot')) {
            $this->appointment->load('timeSlot');
        }

        $timeInfo = '';
        if ($this->appointment->timeSlot) {
            $timeInfo = ' at ' . $this->appointment->timeSlot->start_time . ' - ' . $this->appointment->timeSlot->end_time;
        }

        return [
            'type' => 'emergency_appointment_cancellation',
            'appointment_id' => $this->appointment->id,
            'doctor_name' => $this->doctorName,
            'clinic_name' => $this->appointment->clinic->name,
            'appointment_date' => $this->appointment->appointment_date,
            'reason' => $this->reason,
            'is_emergency' => $this->isEmergency,
            'cancellation_date' => $this->cancellationDate,
            'message' => 'Your appointment with Dr. ' . $this->doctorName .
                        ' on ' . $this->appointment->appointment_date->format('M j, Y') .
                        $timeInfo .
                        ' has been cancelled' .
                        ($this->isEmergency ? ' due to emergency reasons.' : '.')
        ];
    }
}
