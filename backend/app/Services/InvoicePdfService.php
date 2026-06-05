<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentAccount;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class InvoicePdfService
{
    public function __construct(private ReceivableService $receivableService)
    {
    }

    public function make(Invoice $invoice): string
    {
        $invoice->loadMissing([
            'branch',
            'creator',
            'bookings.customer',
            'bookings.bookingDetails.unit',
            'bookings.bookingDetails.costs',
        ]);

        $paymentAccounts = PaymentAccount::query()
            ->where('tenant_id', $invoice->tenant_id)
            ->where('branch_id', $invoice->branch_id)
            ->where('is_active', true)
            ->orderBy('nama_bank')
            ->get()
            ->filter(fn($acc) => $acc->nama_bank && strtolower($acc->nama_bank) !== 'cash')
            ->map(fn($acc) => [
                'nama_bank' => $acc->nama_bank,
                'nomor_rekening' => $acc->nomor_rekening,
                'atas_nama' => $acc->atas_nama,
            ])
            ->values()
            ->all();

        $primaryBooking = $invoice->bookings->first();
        $customer = $primaryBooking?->customer;

        $customerAddressLines = array_values(array_filter([
            $customer?->alamat,
            $customer?->kota,
        ]));

        $customerContactLines = array_values(array_filter([
            $customer?->kontak_1 ? 'Telp: ' . $customer->kontak_1 : null,
            $customer?->kontak_2 ? 'Telp 2: ' . $customer->kontak_2 : null,
            $customer?->email ? 'Email: ' . $customer->email : null,
        ]));

        $items = $this->receivableService->invoiceItems($invoice)->all();
        $paidAmount = $this->receivableService->invoicePaidAmount($invoice);

        $totalAmount = (int) $invoice->total_amount;
        $remainingAmount = max(0, $totalAmount - $paidAmount);

        $branchName = $invoice->branch?->name ?: 'DRENT';

        $invoiceDateRaw = $invoice->generated_at ?? $invoice->created_at;

        $branchLogoBase64 = null;
        if ($invoice->branch?->logo_path) {
            $logoPath = storage_path('app/public/' . ltrim($invoice->branch->logo_path, '/'));
            if (file_exists($logoPath)) {
                $branchLogoBase64 = 'data:image/jpeg;base64,' . base64_encode($this->resizeImage($logoPath, 96, 96));
            }
        }

        $signatureBase64 = null;
        if ($invoice->creator?->signature_path) {
            $sigPath = storage_path('app/public/' . ltrim($invoice->creator->signature_path, '/'));
            if (file_exists($sigPath)) {
                $signatureBase64 = 'data:image/jpeg;base64,' . base64_encode($this->resizeImage($sigPath, 300, 120));
            }
        }

        $data = [
            'invoice' => $invoice,
            'branchName' => $branchName,
            'branchLogoBase64' => $branchLogoBase64,
            'branchAddress' => $invoice->branch?->address,
            'branchPhone' => $invoice->branch?->phone,
            'branchEmail' => $invoice->branch?->email,
            'customerName' => $customer?->nama ?: 'Pelanggan',
            'customerAddressLines' => $customerAddressLines,
            'customerContactLines' => $customerContactLines,
            'invoiceDate' => $this->formatDate($invoiceDateRaw),
            'generatedDate' => $this->formatDateTime($invoiceDateRaw),
            'dueDate' => $this->formatDate($invoice->due_date),
            'items' => $items,
            'paymentAccounts' => $paymentAccounts,
            'paidAmount' => $paidAmount,
            'remainingAmount' => $remainingAmount,
            'statusSeverity' => $this->statusSeverity($invoice->status),
            'authorizedSignName' => $invoice->creator?->name ?: 'Authorised Sign',
            'signatureBase64' => $signatureBase64,
            'termsAndConditions' => $invoice->terms_and_conditions,
            'formatCurrency' => fn($value) => $this->formatCurrency($value),
            'formatDate' => fn($value) => $this->formatDate($value),
        ];

        $pdf = Pdf::loadView('pdf.invoice', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isRemoteEnabled'        => false,
                'isHtml5ParserEnabled'   => false,
                'defaultFont'            => 'DejaVu Sans',
                'isFontSubsettingEnabled' => true,
                'isPhpEnabled'           => false,
                'dpi'                    => 96,
            ]);

        return $pdf->output();
    }

    private function resizeImage(string $path, int $maxWidth, int $maxHeight): string
    {
        if (! extension_loaded('gd')) {
            return file_get_contents($path);
        }

        $info = @getimagesize($path);
        if (! $info) {
            return file_get_contents($path);
        }

        [$origWidth, $origHeight, $type] = $info;

        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_GIF  => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default        => false,
        };

        if (! $src) {
            return file_get_contents($path);
        }

        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight, 1.0);
        $newWidth = (int) round($origWidth * $ratio);
        $newHeight = (int) round($origHeight * $ratio);

        $dst = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $bg = imagecolorallocatealpha($dst, 255, 255, 255, 127);
            imagefill($dst, 0, 0, $bg);
        } else {
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefill($dst, 0, 0, $white);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        imagedestroy($src);

        ob_start();
        imagejpeg($dst, null, 85);
        $data = ob_get_clean();
        imagedestroy($dst);

        return $data;
    }

    private function mimeFromExtension(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }

    private function formatCurrency($value): string
    {
        $amount = (int) ($value ?? 0);
        $negative = $amount < 0;
        $formatted = 'Rp ' . number_format(abs($amount), 0, ',', '.');

        return $negative ? '-' . $formatted : $formatted;
    }

    private function formatDateTime($value): string
    {
        if (! $value) {
            return '-';
        }

        try {
            return Carbon::parse($value)->translatedFormat('d M Y H:i');
        } catch (\Throwable $e) {
            return '-';
        }
    }

    private function formatDate($value): string
    {
        if (! $value) {
            return '-';
        }

        try {
            return Carbon::parse($value)->translatedFormat('d M Y');
        } catch (\Throwable $e) {
            return '-';
        }
    }

    private function statusSeverity(?string $status): string
    {
        return match ($status) {
            'paid' => 'success',
            'partial_paid' => 'info',
            'void' => 'danger',
            default => 'warn',
        };
    }
}
