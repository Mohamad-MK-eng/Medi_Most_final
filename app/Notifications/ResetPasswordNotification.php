<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $code;

    public function __construct($code)
    {
        $this->code = $code;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Password Reset Verification Code')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->line('Your verification code is:')
            ->line('## ' . $this->code)
            ->line('This code will expire in 15 minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }
}
