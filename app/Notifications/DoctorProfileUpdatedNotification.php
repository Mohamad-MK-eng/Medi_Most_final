<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class DoctorProfileUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $adminName;
    public $updatedFields;
    public $updatedAt;

    public function __construct($adminName, $updatedFields)
    {
        $this->adminName = $adminName;
        $this->updatedFields = $updatedFields;
        $this->updatedAt = now();
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $mailMessage = (new MailMessage)
            ->subject('🔧 Your Profile Has Been Updated')
            ->greeting('Hello Dr. ' . $notifiable->name . '!')
            ->line('Your profile information has been updated by the clinic administrator.')
            ->line('**Updated By:** ' . $this->adminName)
            ->line('**Updated On:** ' . $this->updatedAt->format('M j, Y g:i A'));

        if (!empty($this->updatedFields)) {
            $mailMessage->line('**Changes Made:**');
            foreach ($this->updatedFields as $field => $value) {
                $mailMessage->line('- ' . ucfirst(str_replace('_', ' ', $field)) . ': ' . $value);
            }
        }

        $mailMessage->action('View Your Profile', url('/doctor/profile'))
            ->line('If you did not request these changes or notice any discrepancies, please contact the clinic administration immediately.');

        return $mailMessage;
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'doctor_profile_updated',
            'admin_name' => $this->adminName,
            'updated_fields' => $this->updatedFields,
            'updated_at' => $this->updatedAt,
            'message' => 'Your profile has been updated by ' . $this->adminName .
                        '. Changes: ' . implode(', ', array_keys($this->updatedFields))
        ];
    }
}
