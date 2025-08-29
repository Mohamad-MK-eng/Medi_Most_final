<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

trait HandlesFiles
{




    public function uploadFile($file, $fieldName = 'profile_picture')
    {
        try {
            $config = $this->fileHandlingConfig[$fieldName] ?? [
                'directory' => 'user_profile_pictures',
                'allowed_types' => ['jpg', 'jpeg', 'png', 'gif'],
                'max_size' => 3072,
                'default' => 'default-user.jpg'
            ];

            if (!$file->isValid()) {
                throw new \Exception("Invalid file upload");
            }

            $extension = strtolower($file->getClientOriginalExtension());
            if (!in_array($extension, $config['allowed_types'])) {
                throw new \Exception("File type not allowed. Allowed types: " . implode(',', $config['allowed_types']));
            }

            if ($file->getSize() > ($config['max_size'] * 1024)) {
                throw new \Exception("File size exceeds maximum allowed size of {$config['max_size']}KB");
            }



              if (!empty($this->{$fieldName})) {
            $this->deleteFile($fieldName); // This will delete the old file
        }
            $filename = Str::uuid()->toString() . '.' . $extension;
            $directory = trim($config['directory'], '/');

            $path = $file->storeAs($directory, $filename, 'public');

            $this->{$fieldName} = $path;
            $this->save();

            return $path;
        } catch (\Exception $e) {
            Log::error("File upload failed: " . $e->getMessage(), [
                'model' => get_class($this),
                'id' => $this->id,
                'field' => $fieldName,
                'error' => $e->getTraceAsString()
            ]);
            return false;
        }
    }






   public function getFileUrl($fieldName = 'profile_picture')
{
    if (!isset($this->fileHandlingConfig[$fieldName])) {
        return null;
    }

    $config = $this->fileHandlingConfig[$fieldName] ?? [
        'directory' => 'storage/app/public/user_profile_pictures',
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif'],
        'max_size' => 3072,
    ];

    if (empty($this->{$fieldName})) {
        return null;
    }

    $path = ltrim(str_replace(['storage/', 'public/'], '', $this->{$fieldName}), '/');

    if (Storage::disk('public')->exists($path)) {
        return asset('storage/' . $path);
    }

    return null;
}






    public function deleteFile($fieldName = 'profile_picture')
    {
        try {
            if ($this->{$fieldName} && Storage::disk('public')->exists($this->{$fieldName})) {
                Storage::disk('public')->delete($this->{$fieldName});
                $this->{$fieldName} = null;
                $this->save();

                Log::info("File deleted successfully for {$fieldName}", [
                    'model' => get_class($this),
                    'id' => $this->id
                ]);
            }
            return true;
        } catch (\Exception $e) {
            Log::error("File deletion failed: " . $e->getMessage(), [
                'model' => get_class($this),
                'id' => $this->id,
                'field' => $fieldName
            ]);
            return false;
        }
    }



    public function getProfilePictureUrl()
    {
        return $this->getFileUrl('profile_picture');
    }
}
