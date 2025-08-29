<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class WalletFundsAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $amount;
    public $newBalance;
    public $addedBy;
    public $notes;
    public $transactionDate;

    public function __construct($amount, $newBalance, $addedBy, $notes = null)
    {
        $this->amount = $amount;
        $this->newBalance = $newBalance;
        $this->addedBy = $addedBy;
        $this->notes = $notes;
        $this->transactionDate = now();
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Funds Added to Your Wallet')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('We wanted to let you know that funds have been added to your wallet.')
            ->line('**Amount Added:** $' . number_format($this->amount, 2))
            ->line('**New Balance:** $' . number_format($this->newBalance, 2))
            ->line('**Added By:** ' . $this->addedBy)
            ->line('**Transaction Date:** ' . $this->transactionDate->format('M j, Y g:i A'))
            ->lineIf($this->notes, '**Notes:** ' . $this->notes)
            ->action('View Your Wallet', url('/patient/wallet'))
            ->line('Thank you for choosing our clinic!');
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'wallet_funds_added',
            'amount' => $this->amount,
            'new_balance' => $this->newBalance,
            'added_by' => $this->addedBy,
            'notes' => $this->notes,
            'transaction_date' => $this->transactionDate,
            'message' => 'Funds added to your wallet: $' . number_format($this->amount, 2),
        ];
    }

    public function toArray($notifiable)
    {
        return [
            'amount' => $this->amount,
            'new_balance' => $this->newBalance,
            'added_by' => $this->addedBy,
            'notes' => $this->notes,
            'transaction_date' => $this->transactionDate,
        ];
    }
}
