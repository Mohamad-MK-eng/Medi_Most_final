<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Payment;

class PaymentConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
        $this->afterCommit = true;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Confirmation')
            ->line('Your payment has been successfully processed.')
            ->line('Amount: ' . number_format($this->payment->amount, 2))
            ->line('Method: ' . ucfirst($this->payment->method))
            ->line('Appointment Date: ' . $this->payment->appointment->appointment_date->format('M j, Y g:i A'))
            ->line('Doctor: ' . $this->payment->appointment->doctor->user->full_name)
            ->action('View Payment Details', url('/payments/' . $this->payment->id))
            ->line('Thank you for using our service!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'amount' => $this->payment->amount,
            'method' => $this->payment->method,
            'appointment_id' => $this->payment->appointment_id,
            'doctor_name' => $this->payment->appointment->doctor->user->full_name,
            'appointment_date' => $this->payment->appointment->appointment_date->format('Y-m-d H:i:s'),
            'message' => 'Your payment has been processed successfully',
            'url' => '/payments/' . $this->payment->id
        ];
    }
}
