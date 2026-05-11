<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'tenant_id' => $this->tenant_id,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->whenLoaded('branch', fn() => $this->branch->name),
            'created_at' => $this->created_at,
        ];
    }
}
