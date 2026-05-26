<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MemberService
{
    public function list($filters = [])
    {
        $query = Member::with('customer', 'surveyor');

        if (isset($filters['status_member'])) {
            $query->where('status_member', $filters['status_member']);
        }

        if (isset($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }

    public function createMember(array $data)
    {
        return DB::transaction(function () use ($data) {
            $data = $this->handleFileUploads($data);
            $member = Member::create($data);
            $this->syncCustomerMembershipStatus($member);

            return $member;
        });
    }

    public function updateMember(Member $member, array $data)
    {
        return DB::transaction(function () use ($member, $data) {
            $data = $this->handleFileUploads($data, $member);
            $member->update($data);
            $this->syncCustomerMembershipStatus($member->refresh());

            return $member;
        });
    }

    public function activateMember(Member $member)
    {
        return DB::transaction(function () use ($member) {
            if (!$member->id_member) {
                $member->id_member = 'MBR-' . date('Y') . '-' . strtoupper(Str::random(4));
            }

            $member->status_member = 'Aktif';
            $member->tanggal_aktif = now();
            $member->tanggal_exp = now()->addYear();
            $member->save();

            $this->syncCustomerMembershipStatus($member->refresh());

            return $member;
        });
    }

    public function updateStatus(Member $member, string $status)
    {
        return DB::transaction(function () use ($member, $status) {
            $member->status_member = $status;
            if ($status === 'Aktif') {
                if (!$member->id_member) {
                    $member->id_member = 'MBR-' . date('Y') . '-' . strtoupper(Str::random(4));
                }
                if (!$member->tanggal_aktif) {
                    $member->tanggal_aktif = now();
                }
                if (!$member->tanggal_exp) {
                    $member->tanggal_exp = now()->addYear();
                }
            }
            $member->save();

            $this->syncCustomerMembershipStatus($member->refresh());

            return $member;
        });
    }

    public function extendMember(Member $member, array $data)
    {
        return DB::transaction(function () use ($member, $data) {
            $oldExp = $member->tanggal_exp;
            
            // Create extension record
            $extension = $member->extensions()->create([
                'old_exp_date' => $oldExp,
                'new_exp_date' => $data['new_exp_date'],
                'catatan' => $data['catatan'],
                'created_by' => auth()->id(),
            ]);

            // Update member expiration date
            $member->tanggal_exp = $data['new_exp_date'];
            // If currently not active/pending, let's keep status_member as is, or if they extend they are active
            if ($member->status_member !== 'Aktif') {
                $member->status_member = 'Aktif';
            }
            $member->save();

            $this->syncCustomerMembershipStatus($member->refresh());

            return $extension;
        });
    }

    public function getExtensions(Member $member)
    {
        return $member->extensions()->with('creator')->get();
    }

    protected function syncCustomerMembershipStatus(Member $member): void
    {
        $member->loadMissing('customer');

        if (!$member->customer) {
            return;
        }

        $updates = ['has_apply_member' => true];

        if ($member->status_member === 'Aktif') {
            if (!in_array($member->customer->status, ['Redflag', 'Blacklist'], true)) {
                $updates['status'] = 'Member';
            }
        } elseif ($member->customer->status === 'Member') {
            $updates['status'] = 'Normal';
        }

        $member->customer->forceFill($updates)->save();
    }

    protected function handleFileUploads(array $data, Member $member = null)
    {
        // SECURE STORAGE: Store in 'local' disk (storage/app/members/documents)
        // This is not publicly accessible.
        $disk = 'local';
        $path = 'members/documents';

        if (isset($data['foto_wajah']) && $data['foto_wajah'] instanceof \Illuminate\Http\UploadedFile) {
            if ($member && $member->foto_wajah) {
                Storage::disk($disk)->delete($member->foto_wajah);
            }
            $data['foto_wajah'] = $data['foto_wajah']->store($path, $disk);
        }

        if (isset($data['dokumen_identitas']) && $data['dokumen_identitas'] instanceof \Illuminate\Http\UploadedFile) {
            if ($member && $member->dokumen_identitas) {
                Storage::disk($disk)->delete($member->dokumen_identitas);
            }
            $data['dokumen_identitas'] = $data['dokumen_identitas']->store($path, $disk);
        }

        if (isset($data['dokumen_pendukung_files']) && is_array($data['dokumen_pendukung_files'])) {
            $existingDocs = $member ? ($member->dokumen_pendukung ?? []) : [];
            $newDocs = [];
            foreach ($data['dokumen_pendukung_files'] as $file) {
                if ($file instanceof \Illuminate\Http\UploadedFile) {
                    $newDocs[] = $file->store($path, $disk);
                }
            }
            $data['dokumen_pendukung'] = array_merge($existingDocs, $newDocs);
        }

        return $data;
    }

    public function getDocumentPath(Member $member, $type)
    {
        $path = null;
        switch ($type) {
            case 'foto_wajah':
                $path = $member->foto_wajah;
                break;
            case 'dokumen_identitas':
                $path = $member->dokumen_identitas;
                break;
            default:
                // Handle documents from array if needed
                if (str_starts_with($type, 'pendukung_')) {
                    $index = (int) str_replace('pendukung_', '', $type);
                    $path = $member->dokumen_pendukung[$index] ?? null;
                }
                break;
        }

        return $path;
    }
}
