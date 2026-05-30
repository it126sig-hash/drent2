<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BranchService
{
    public function getAll(array $filters = [])
    {
        $user = Auth::user();

        $query = Branch::with('city')
            ->where('tenant_id', $user->tenant_id);

        // Admin branch hanya melihat branch sendiri
        if ($user->role === 'admin_branch') {
            $query->where('id', $user->branch_id);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->paginate($filters['per_page'] ?? 50);
    }

    public function create(array $data, ?UploadedFile $logo = null): Branch
    {
        $data['tenant_id'] = Auth::user()->tenant_id;

        // Bersihkan field yang bukan kolom DB
        unset($data['logo'], $data['remove_logo']);

        if ($logo) {
            $data['logo_path'] = $logo->store('branch-logos', 'public');
        }

        $branch = Branch::create($data);

        return $branch->fresh(['city']);
    }

    public function update(Branch $branch, array $data, ?UploadedFile $logo = null): Branch
    {
        $removeLogo = filter_var($data['remove_logo'] ?? false, FILTER_VALIDATE_BOOLEAN);

        unset($data['logo'], $data['remove_logo']);

        if ($removeLogo && $branch->logo_path) {
            Storage::disk('public')->delete($branch->logo_path);
            $data['logo_path'] = null;
        }

        if ($logo) {
            if ($branch->logo_path) {
                Storage::disk('public')->delete($branch->logo_path);
            }
            $data['logo_path'] = $logo->store('branch-logos', 'public');
        }

        $branch->update($data);

        return $branch->fresh(['city']);
    }

    public function delete(Branch $branch): bool
    {
        // Soft delete; file logo tetap dipertahankan untuk audit (tidak ikut dihapus)
        return (bool) $branch->delete();
    }
}
