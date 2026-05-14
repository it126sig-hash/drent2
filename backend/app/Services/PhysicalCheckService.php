<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PhysicalCheck;
use App\Models\PhysicalCheckItem;
use App\Models\PhysicalCheckPhoto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PhysicalCheckService
{
    public const RELATIONS = [
        'booking.customer',
        'booking.bookingDetails.unit.rentalOwner',
        'booking.bookingDetails.driver',
        'requestedBy',
        'inspectedBy',
        'skippedBy',
        'sections',
        'photos',
        'checklists',
        'signatures',
        'activities.user',
    ];

    public function listOperationalBookings(array $filters = [])
    {
        $user = Auth::user();

        $query = Booking::query()
            ->with([
                'customer',
                'bookingDetails.unit.rentalOwner',
                'bookingDetails.driver',
                'physicalChecks',
            ])
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('status', ['waiting_list', 'rental_unit']);

        if (($filters['branch_id'] ?? null) && $filters['branch_id'] !== 'all') {
            $query->where('branch_id', $filters['branch_id']);
        } elseif ($user->role !== 'superadmin') {
            $query->where('branch_id', $user->branch_id);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('kode_booking', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn($customer) => $customer->where('nama', 'like', "%{$search}%"))
                    ->orWhereHas('bookingDetails.unit', function ($unit) use ($search) {
                        $unit->where('merk', 'like', "%{$search}%")
                            ->orWhere('tipe', 'like', "%{$search}%")
                            ->orWhere('no_polisi', 'like', "%{$search}%");
                    });
            });
        }

        return $query->latest()->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function findForBooking(Booking $booking, string $type): ?PhysicalCheck
    {
        return $booking->physicalChecks()
            ->with(self::RELATIONS)
            ->where('type', $type)
            ->latest()
            ->first();
    }

    public function requestForBooking(Booking $booking, string $type): PhysicalCheck
    {
        $this->ensureBookingStatusMatchesType($booking, $type);

        $existing = $booking->physicalChecks()
            ->where('type', $type)
            ->whereIn('status', ['requested', 'completed'])
            ->latest()
            ->first();

        if ($existing) {
            if (! $existing->public_token) {
                $existing->forceFill(['public_token' => $this->makePublicToken()])->save();
            }

            $this->recordActivity($existing, 'request_reused', ['type' => $type]);

            return $existing->load(self::RELATIONS);
        }

        $check = $booking->physicalChecks()->create([
            'tenant_id' => $booking->tenant_id,
            'branch_id' => $booking->branch_id,
            'booking_detail_id' => $this->displayDetail($booking)?->id,
            'type' => $type,
            'status' => 'requested',
            'public_token' => $this->makePublicToken(),
            'requested_at' => now(),
            'requested_by' => Auth::id(),
        ]);

        $this->recordActivity($check, 'requested', ['type' => $type]);

        return $check->load(self::RELATIONS);
    }

    public function skipForBooking(Booking $booking, string $type): PhysicalCheck
    {
        $this->ensureBookingStatusMatchesType($booking, $type);

        $check = $booking->physicalChecks()
            ->where('type', $type)
            ->latest()
            ->first();

        if (! $check) {
            $check = $booking->physicalChecks()->make([
                'tenant_id' => $booking->tenant_id,
                'branch_id' => $booking->branch_id,
                'booking_detail_id' => $this->displayDetail($booking)?->id,
                'type' => $type,
            ]);
        }

        $check->fill([
            'status' => 'skipped',
            'skipped_at' => now(),
            'skipped_by' => Auth::id(),
        ]);
        $check->save();

        return $check->load(self::RELATIONS);
    }

    public function hasCompletedCheck(Booking $booking, string $type): bool
    {
        return $booking->physicalChecks()
            ->where('type', $type)
            ->where('status', 'completed')
            ->exists();
    }

    public function storeCompleted(array $data, ?Request $request = null, ?PhysicalCheck $publicCheck = null): PhysicalCheck
    {
        $booking = Booking::with(['bookingDetails'])->findOrFail($data['booking_id']);
        $this->ensureBookingStatusMatchesType($booking, $data['type']);
        $this->ensureWithinInspectionWindow($booking, $data['type']);

        if ($publicCheck) {
            if ($publicCheck->booking_id !== $booking->id || $publicCheck->type !== $data['type']) {
                throw new UnprocessableEntityHttpException('Link cek fisik tidak sesuai dengan data yang dikirim.');
            }

            $this->verifyOtp($publicCheck, $data['customer_email'] ?? null, $data['otp_code'] ?? null, $request);
        }

        return DB::transaction(function () use ($booking, $data, $request, $publicCheck) {
            $check = $publicCheck ?: $booking->physicalChecks()
                ->where('type', $data['type'])
                ->latest()
                ->first();

            if (! $check) {
                $check = $booking->physicalChecks()->make([
                    'tenant_id' => $booking->tenant_id,
                    'branch_id' => $booking->branch_id,
                    'type' => $data['type'],
                    'public_token' => $this->makePublicToken(),
                    'requested_at' => now(),
                    'requested_by' => Auth::id(),
                ]);
            }

            $check->fill([
                'booking_detail_id' => $this->displayDetail($booking)?->id,
                'status' => 'completed',
                'km_odometer' => $data['km_odometer'],
                'fuel_level' => $data['fuel_level'] ?? null,
                'fuel_marker_x' => $data['fuel_marker_x'] ?? null,
                'fuel_marker_y' => $data['fuel_marker_y'] ?? null,
                'notes' => $data['notes'] ?? null,
                'inspected_at' => now(),
                'inspected_by' => Auth::id(),
            ]);
            $check->save();

            $check->sections()->delete();
            foreach ($data['sections'] as $section) {
                $check->sections()->create([
                    'section' => $section['section'],
                    'notes' => $section['notes'] ?? null,
                ]);
            }

            $this->replacePhotos($check, $data['photos'] ?? []);

            $check->checklists()->delete();
            foreach ($data['checklist'] as $item) {
                $check->checklists()->create([
                    'physical_check_item_id' => $item['physical_check_item_id'] ?? null,
                    'item_label' => $item['item_label'],
                    'is_present' => $item['is_present'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $this->replaceSignatures($check, $data['signatures']);

            $this->recordActivity($check, $publicCheck ? 'public_submitted' : 'submitted', [
                'photos_count' => count($data['photos'] ?? []),
                'checklist_count' => count($data['checklist'] ?? []),
            ], $request, $publicCheck ? 'customer' : null);

            return $check->load(self::RELATIONS);
        });
    }

    public function findPublic(string $token): PhysicalCheck
    {
        $check = PhysicalCheck::query()
            ->with(self::RELATIONS)
            ->where('public_token', $token)
            ->firstOrFail();

        $check->forceFill(['public_last_opened_at' => now()])->save();
        $this->recordActivity($check, 'public_opened', [], request(), 'customer');

        return $check;
    }

    public function publicItems(PhysicalCheck $check)
    {
        return PhysicalCheckItem::query()
            ->where('tenant_id', $check->tenant_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function requestOtp(PhysicalCheck $check, Request $request): void
    {
        $email = $check->booking?->customer?->email;
        if (! $email) {
            throw new UnprocessableEntityHttpException('Email penyewa belum tersedia pada data customer.');
        }

        $code = (string) random_int(100000, 999999);

        $check->otps()->whereNull('consumed_at')->update(['consumed_at' => now()]);
        $check->otps()->create([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
            'requested_ip' => $request->ip(),
            'requested_user_agent' => (string) $request->userAgent(),
        ]);

        Mail::raw(
            "Kode OTP cek fisik DRENT untuk booking {$check->booking?->kode_booking}: {$code}. Kode berlaku 10 menit.",
            fn($message) => $message->to($email)->subject('Kode OTP Cek Fisik DRENT')
        );

        $this->recordActivity($check, 'otp_requested', ['email' => $this->maskEmail($email)], $request, 'customer');
    }

    public function recordActivity(
        PhysicalCheck $check,
        string $event,
        array $context = [],
        ?Request $request = null,
        ?string $actorType = null
    ): void {
        $user = Auth::user();

        $check->activities()->create([
            'user_id' => $user?->id,
            'actor_type' => $actorType ?: ($user ? 'user' : 'system'),
            'event' => $event,
            'context' => $context ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? (string) $request->userAgent() : null,
        ]);
    }

    public function assertCompletedOrRequest(Booking $booking, string $type): void
    {
        if ($this->hasCompletedCheck($booking, $type)) {
            return;
        }

        $this->requestForBooking($booking, $type);

        $label = $type === 'departure' ? 'keberangkatan' : 'pengembalian';
        throw new UnprocessableEntityHttpException(
            "Cek fisik {$label} belum selesai. Request cek fisik sudah dibuat."
        );
    }

    public function displayDetail(Booking $booking)
    {
        if (! $booking->relationLoaded('bookingDetails')) {
            $booking->load('bookingDetails');
        }

        $details = $booking->bookingDetails;

        return $details->firstWhere('status', 'aktif')
            ?? $details->firstWhere('detail_type', 'initial')
            ?? $details->firstWhere('status', 'draft')
            ?? $details->last();
    }

    private function replacePhotos(PhysicalCheck $check, array $photos): void
    {
        foreach ($check->photos as $photo) {
            $this->deletePhotoFiles($photo);
        }
        $check->photos()->delete();

        foreach ($photos as $photo) {
            $path = $this->storeBase64Image(
                $photo['image_base64'],
                "physical-checks/{$check->booking_id}/{$check->type}/photos"
            );

            $annotatedPath = null;
            if (! empty($photo['annotated_base64'])) {
                $annotatedPath = $this->storeBase64Image(
                    $photo['annotated_base64'],
                    "physical-checks/{$check->booking_id}/{$check->type}/photos"
                );
            }

            $check->photos()->create([
                'section' => $photo['section'],
                'path' => $path,
                'annotated_path' => $annotatedPath,
                'notes' => $photo['notes'] ?? null,
            ]);
        }
    }

    private function replaceSignatures(PhysicalCheck $check, array $signatures): void
    {
        foreach ($check->signatures as $signature) {
            if (Storage::disk('public')->exists($signature->signature_path)) {
                Storage::disk('public')->delete($signature->signature_path);
            }
        }
        $check->signatures()->delete();

        foreach ($signatures as $signature) {
            $path = $this->storeBase64Image(
                $signature['signature_base64'],
                "physical-checks/{$check->booking_id}/{$check->type}/signatures"
            );

            $check->signatures()->create([
                'signer_type' => $signature['signer_type'],
                'signer_name' => $signature['signer_name'] ?? null,
                'signature_path' => $path,
                'signed_at' => now(),
            ]);
        }
    }

    private function deletePhotoFiles(PhysicalCheckPhoto $photo): void
    {
        foreach ([$photo->path, $photo->annotated_path] as $path) {
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function storeBase64Image(string $base64, string $directory): string
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
            $extension = strtolower($matches[1]);
            $base64 = substr($base64, strpos($base64, ',') + 1);
        } else {
            $extension = 'png';
        }

        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        $binary = base64_decode($base64, true);
        if ($binary === false) {
            throw new UnprocessableEntityHttpException('Format gambar cek fisik tidak valid.');
        }

        $path = trim($directory, '/') . '/' . Str::uuid() . '.' . $extension;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    private function verifyOtp(PhysicalCheck $check, ?string $email, ?string $code, ?Request $request): void
    {
        $expectedEmail = $check->booking?->customer?->email;
        if (! $expectedEmail || ! $email || strcasecmp($expectedEmail, $email) !== 0) {
            $this->recordActivity($check, 'otp_failed', ['reason' => 'email_mismatch'], $request, 'customer');
            throw new UnprocessableEntityHttpException('Email OTP tidak sesuai dengan email penyewa.');
        }

        $otp = $check->otps()
            ->where('email', $expectedEmail)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $otp || $otp->expires_at->isPast()) {
            $this->recordActivity($check, 'otp_failed', ['reason' => 'expired_or_missing'], $request, 'customer');
            throw new UnprocessableEntityHttpException('Kode OTP sudah kedaluwarsa. Kirim ulang OTP.');
        }

        if ($otp->attempts >= 5 || ! $code || ! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            $this->recordActivity($check, 'otp_failed', ['reason' => 'invalid_code'], $request, 'customer');
            throw new UnprocessableEntityHttpException('Kode OTP tidak valid.');
        }

        $otp->forceFill(['consumed_at' => now()])->save();
        $this->recordActivity($check, 'otp_verified', ['email' => $this->maskEmail($email)], $request, 'customer');
    }

    private function makePublicToken(): string
    {
        do {
            $token = Str::random(48);
        } while (PhysicalCheck::where('public_token', $token)->exists());

        return $token;
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $prefix = Str::substr($name, 0, 2);

        return $prefix . '***@' . $domain;
    }

    private function ensureBookingStatusMatchesType(Booking $booking, string $type): void
    {
        $expected = $type === 'departure' ? 'waiting_list' : 'rental_unit';

        if ($booking->status !== $expected) {
            $label = $type === 'departure' ? 'keberangkatan' : 'pengembalian';
            $statusLabel = $type === 'departure' ? 'Waiting List' : 'Rental Unit';
            throw new UnprocessableEntityHttpException(
                "Cek fisik {$label} hanya bisa dilakukan saat booking berstatus {$statusLabel}."
            );
        }
    }

    private function ensureWithinInspectionWindow(Booking $booking, string $type): void
    {
        $detail = $this->displayDetail($booking);
        if (! $detail) {
            throw new UnprocessableEntityHttpException('Detail kendaraan belum tersedia.');
        }

        $dateColumn = $type === 'departure' ? $detail->tgl_sewa : $detail->tgl_kembali;
        if (! $dateColumn) {
            throw new UnprocessableEntityHttpException('Tanggal sewa/kembali belum tersedia.');
        }

        $target = Carbon::parse($dateColumn)->startOfDay();
        $start = $type === 'departure' ? $target->copy()->subDay() : $target->copy();
        $end = $type === 'departure' ? $target->copy()->endOfDay() : $target->copy()->addDay()->endOfDay();
        $today = Carbon::now(config('app.timezone'))->startOfDay();

        if ($today->lt($start->copy()->startOfDay()) || $today->gt($end->copy()->startOfDay())) {
            $message = $type === 'departure'
                ? 'Cek fisik keberangkatan hanya bisa dilakukan H-1 atau hari H tanggal sewa.'
                : 'Cek fisik pengembalian hanya bisa dilakukan pada tanggal kembali sampai H+1.';

            throw new UnprocessableEntityHttpException($message);
        }
    }
}
