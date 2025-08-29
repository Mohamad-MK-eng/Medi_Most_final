<?php

// app/Models/MedicalCenterWalletTransaction.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalCenterWalletTransaction extends Model
{
    protected $fillable = [
        'medical_wallet_id',
        'amount',
        'type',
        'reference',
        'balance_before',
        'balance_after',
        'notes',
        'clinic_id'
    ];

    public function wallet()
    {
return $this->belongsTo(MedicalCenterWallet::class, 'medical_wallet_id');    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }
}
