<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserProfileService
{
    public function updateProfile(User $user, array $data, ?UploadedFile $photo = null): User
    {
        $removePhoto = filter_var($data['remove_foto_profile'] ?? false, FILTER_VALIDATE_BOOLEAN);

        unset($data['foto_profile'], $data['remove_foto_profile']);

        if ($removePhoto && $user->foto_profile_path) {
            Storage::disk('public')->delete($user->foto_profile_path);
            $data['foto_profile_path'] = null;
        }

        if ($photo) {
            if ($user->foto_profile_path) {
                Storage::disk('public')->delete($user->foto_profile_path);
            }

            $data['foto_profile_path'] = $photo->store('user-profiles', 'public');
        }

        $user->update($data);

        return $user->fresh(['branch', 'driver']);
    }

    public function updatePassword(User $user, string $password): User
    {
        $user->update(['password' => Hash::make($password)]);

        return $user->fresh(['branch', 'driver']);
    }
}
