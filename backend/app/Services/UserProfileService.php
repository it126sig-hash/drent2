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
        $removeSignature = filter_var($data['remove_signature'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $signatureData = $data['signature_data'] ?? null;

        unset($data['foto_profile'], $data['remove_foto_profile'], $data['signature_data'], $data['remove_signature']);

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

        // Handle signature (base64 PNG → stored file)
        if ($removeSignature && !$signatureData) {
            if ($user->signature_path) {
                Storage::disk('public')->delete($user->signature_path);
            }
            $data['signature_path'] = null;
        } elseif ($signatureData && str_starts_with($signatureData, 'data:image/')) {
            if ($user->signature_path) {
                Storage::disk('public')->delete($user->signature_path);
            }
            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $signatureData));
            $filename = 'user-signatures/' . $user->id . '_' . time() . '.png';
            Storage::disk('public')->put($filename, $imageData);
            $data['signature_path'] = $filename;
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
