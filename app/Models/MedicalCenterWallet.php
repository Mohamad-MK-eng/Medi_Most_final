<?php

// app/Models/MedicalCenterWallet.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalCenterWallet extends Model
{
    protected $fillable = ['balance'];

    public function transactions()
    {
 return $this->hasMany(MedicalCenterWalletTransaction::class, 'medical_wallet_id');
    }

    public function payments()
    {
        return $this->hasManyThrough(Payment::class, Appointment::class);
    }


}
