<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class PatientUnblockedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $unblockedBy;
    public $unblockedAt;

    public function __construct($unblockedBy)
    {
        $this->unblockedBy = $unblockedBy;
        $this->unblockedAt = now();
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('✅ Account Unblocked')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('We are pleased to inform you that your account has been unblocked.')
            ->line('**Unblocked By:** ' . $this->unblockedBy)
            ->line('**Unblocked On:** ' . $this->unblockedAt->format('M j, Y g:i A'))
            ->line('Your account access has been fully restored and you can now:')
            ->line('• Book new appointments')
            ->line('• Manage your existing appointments')
            ->line('• Use all clinic services')
            ->action('Book New Appointment', url('/appointments'))
            ->line('Thank you for resolving this matter with us. We look forward to serving you again.');
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'patient_unblocked',
            'unblocked_by' => $this->unblockedBy,
            'unblocked_at' => $this->unblockedAt,
            'message' => 'Your account has been unblocked by ' . $this->unblockedBy . '. You can now book appointments again.'
        ];
    }
}
