<?php

namespace App\Services;

use App\Models\FinanceCategory;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class FinanceCategoryService
{
    public function getAll(array $filters = [])
    {
        $user = Auth::user();

        $query = FinanceCategory::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($this->branchId($filters, $user), fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when(isset($filters['is_active']), fn ($query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('sort_order')
            ->orderBy('name');

        return $query->paginate((int) ($filters['per_page'] ?? 100));
    }

    public function create(array $data): FinanceCategory
    {
        $user = Auth::user();
        $data['tenant_id'] = $user->tenant_id;
        $data['branch_id'] = $this->branchId($data, $user) ?? $user->branch_id;

        return FinanceCategory::create($data);
    }

    public function update(FinanceCategory $category, array $data): FinanceCategory
    {
        $user = Auth::user();
        if (isset($data['branch_id']) && $user->role !== 'superadmin') {
            $data['branch_id'] = $user->branch_id;
        }

        $category->update($data);

        return $category;
    }

    public function delete(FinanceCategory $category): bool
    {
        return (bool) $category->delete();
    }

    private function branchId(array $filters, User $user): ?int
    {
        if ($user->role !== 'superadmin') {
            return $user->branch_id;
        }

        return isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
    }
}
