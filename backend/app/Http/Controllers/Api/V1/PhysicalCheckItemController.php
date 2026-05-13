<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PhysicalCheckItemResource;
use App\Models\PhysicalCheck;
use App\Models\PhysicalCheckItem;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;

class PhysicalCheckItemController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', PhysicalCheck::class);

        $this->ensureDefaultItems();

        $items = PhysicalCheckItem::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return PhysicalCheckItemResource::collection($items);
    }

    private function ensureDefaultItems(): void
    {
        $tenantId = auth()->user()->tenant_id;

        if (PhysicalCheckItem::where('tenant_id', $tenantId)->exists()) {
            return;
        }

        $items = [
            'Ban serep',
            'Dongkrak',
            'Toolkit',
            'Segitiga pengaman',
            'STNK',
            'Kunci roda',
            'Karpet kabin',
            'Buku manual/servis',
        ];

        foreach ($items as $index => $name) {
            PhysicalCheckItem::create([
                'tenant_id' => $tenantId,
                'name' => $name,
                'code' => Str::slug($name),
                'is_required' => true,
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
