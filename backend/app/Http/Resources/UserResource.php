<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'tenant_id'   => $this->tenant_id,
            'branch_id'   => $this->branch_id,
            'branch_name' => $this->branch?->name,
            'name'        => $this->name,
            'email'       => $this->email,
            'role'        => $this->role,
            'role_label'  => $this->role_label,
            'is_active'   => (bool) $this->is_active,
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}
