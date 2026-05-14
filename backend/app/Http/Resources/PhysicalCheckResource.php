<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PhysicalCheckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'booking_detail_id' => $this->booking_detail_id,
            'type' => $this->type,
            'status' => $this->status,
            'public_token' => $this->public_token,
            'km_odometer' => $this->km_odometer,
            'fuel_level' => $this->fuel_level,
            'fuel_marker_x' => $this->fuel_marker_x,
            'fuel_marker_y' => $this->fuel_marker_y,
            'notes' => $this->notes,
            'requested_at' => $this->requested_at?->toISOString(),
            'inspected_at' => $this->inspected_at?->toISOString(),
            'skipped_at' => $this->skipped_at?->toISOString(),
            'requested_by_user' => $this->userPayload('requestedBy'),
            'inspected_by_user' => $this->userPayload('inspectedBy'),
            'skipped_by_user' => $this->userPayload('skippedBy'),
            'sections' => $this->whenLoaded('sections', fn() =>
                $this->sections->map(fn($section) => [
                    'id' => $section->id,
                    'section' => $section->section,
                    'notes' => $section->notes,
                ])->values()
            ),
            'photos' => $this->whenLoaded('photos', fn() =>
                $this->photos->map(fn($photo) => [
                    'id' => $photo->id,
                    'section' => $photo->section,
                    'notes' => $photo->notes,
                    'url' => Storage::disk('public')->url($photo->path),
                    'annotated_url' => $photo->annotated_path
                        ? Storage::disk('public')->url($photo->annotated_path)
                        : null,
                ])->values()
            ),
            'checklist' => $this->whenLoaded('checklists', fn() =>
                $this->checklists->map(fn($item) => [
                    'id' => $item->id,
                    'physical_check_item_id' => $item->physical_check_item_id,
                    'item_label' => $item->item_label,
                    'is_present' => (bool) $item->is_present,
                    'notes' => $item->notes,
                ])->values()
            ),
            'signatures' => $this->whenLoaded('signatures', fn() =>
                $this->signatures->map(fn($signature) => [
                    'id' => $signature->id,
                    'signer_type' => $signature->signer_type,
                    'signer_name' => $signature->signer_name,
                    'signed_at' => $signature->signed_at?->toISOString(),
                    'url' => Storage::disk('public')->url($signature->signature_path),
                ])->values()
            ),
            'activities' => $this->whenLoaded('activities', fn() =>
                $this->activities->sortByDesc('id')->map(fn($activity) => [
                    'id' => $activity->id,
                    'event' => $activity->event,
                    'actor_type' => $activity->actor_type,
                    'context' => $activity->context,
                    'created_at' => $activity->created_at?->toISOString(),
                    'user' => $activity->relationLoaded('user') && $activity->user ? [
                        'id' => $activity->user->id,
                        'name' => $activity->user->name,
                    ] : null,
                ])->values()
            ),
        ];
    }

    private function userPayload(string $relation)
    {
        return $this->whenLoaded($relation, function () use ($relation) {
            $user = $this->{$relation};

            return $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ] : null;
        });
    }
}
