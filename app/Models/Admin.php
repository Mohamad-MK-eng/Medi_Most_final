<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Admin extends Model
{
   protected $fillable = [
    'first_name',
    'last_name',
    'email',
    'password',
    'role_id',
    'phone_number',
    'address',
    'gender',
];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
