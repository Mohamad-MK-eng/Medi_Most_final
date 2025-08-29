<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Storage;

class Clinic extends Model
{


    protected $fillable = [
        'name',
        'location',
        'description',
        'opening_time',
        'closing_time',
        'image_path',
        'specialty'
    ];


    protected $fileHandlingConfig = [
        'image_path' => [
            'directory' => 'clinic_images',
            'allowed_types' => ['jpg', 'jpeg', 'png', 'svg'],
            'max_size' => 5120, // 5MB
            'default' => 'default-clinic.jpg'
        ]
    ];




    public function doctors()
    {
        return $this->hasMany(Doctor::class);
    }





    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }


public function wallet()
{
    return $this->hasOne(MedicalCenterWallet::class);
}



   public function getIconUrl()
    {
        if (!$this->image_path) {
            return asset('storage/clinic_icons/default.jpg');
        }

        return url('storage/clinic_icons/' . basename($this->image_path));
    }

  public function uploadIcon($iconFile)
{
    try {
        if ($this->image_path) {
            $oldFilename = basename($this->image_path);
            Storage::disk('public')->delete('clinic_icons/' . $oldFilename);
        }

        $path = $iconFile->store('clinic_icons', 'public');
        $filename = basename($path);

        $this->image_path = $filename; // تخزين اسم الملف فقط
        $this->save();

        return true;
    } catch (\Exception $e) {
        logger()->error('Clinic icon upload failed: ' . $e->getMessage());
        return false;
    }
}
}
