<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class PatientBlockedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $absentCount;
    public $blockedAt;

    public function __construct($absentCount)
    {
        $this->absentCount = $absentCount;
        $this->blockedAt = now();
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('⚠️ Account Temporarily Blocked')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('We regret to inform you that your account has been temporarily blocked.')
            ->line('**Reason:** You have missed ' . $this->absentCount . ' appointments.')
            ->line('**Blocked On:** ' . $this->blockedAt->format('M j, Y g:i A'))
            ->line('As a result, you will not be able to book new appointments until the issue is resolved.')
            ->line('**What to do next:**')
            ->line('1. Please contact our clinic center to discuss your situation')
            ->line('2. We can help you reschedule any future appointments')
            ->line('3. Once resolved, your account will be unblocked')
            ->action('Contact Clinic Center', url('/contact'))
            ->line('We understand that sometimes circumstances prevent you from attending appointments. Please reach out to us so we can help.');
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'patient_blocked',
            'absent_count' => $this->absentCount,
            'blocked_at' => $this->blockedAt,
            'message' => 'Your account has been blocked due to ' . $this->absentCount . ' missed appointments. Please contact the clinic center.'
        ];
    }
}
