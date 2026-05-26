<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberExtensionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'old_exp_date' => $this->old_exp_date?->format('Y-m-d'),
            'new_exp_date' => $this->new_exp_date?->format('Y-m-d'),
            'catatan' => $this->catatan,
            'created_by' => $this->created_by,
            'creator' => new V1\UserResource($this->whenLoaded('creator')),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
