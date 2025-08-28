<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'secretary_id',
        'amount',
        'type',
        'reference',
        'balance_before',
        'balance_after',
        'notes',
        'admin_id'
    ];


    protected $casts = [
    'amount' => 'decimal:2',
    'balance_before' => 'decimal:2',
    'balance_after' => 'decimal:2'
];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }



public function secretary()
{
    return $this->belongsTo(Secretary::class);
}




    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }





public function payment()
{
    return $this->belongsTo(Payment::class, 'reference', 'transaction_id')
        ->where('transaction_id', 'like', 'WALLET-%');
}


    public static $validTypes = ['deposit', 'payment', 'refund', 'withdrawal', 'fee'];

    public static function isValidType($type)
    {
        return in_array($type, self::$validTypes);
    }
}
