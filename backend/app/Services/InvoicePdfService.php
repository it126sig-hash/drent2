<?php

namespace App\Services;

use App\Models\Invoice;

class InvoicePdfService
{
    public function make(Invoice $invoice): string
    {
        $invoice->loadMissing(['bookings.customer', 'payments.paymentAccount']);

        $lines = [
            'DRENT Vibe',
            'INVOICE',
            'Nomor: ' . $invoice->invoice_number,
            'Status: ' . strtoupper(str_replace('_', ' ', $invoice->status)),
            'Tanggal Generate: ' . $invoice->generated_at?->format('d M Y H:i'),
            'Due Date: ' . ($invoice->due_date?->format('d M Y') ?? '-'),
            '',
            'Booking:',
        ];

        foreach ($invoice->bookings as $booking) {
            $lines[] = '- ' . $booking->kode_booking . ' | ' . ($booking->customer?->nama ?? '-') . ' | ' . $this->rupiah((int) $booking->pivot->amount);
        }

        $lines = array_merge($lines, [
            '',
            'Ringkasan:',
            'Total Invoice: ' . $this->rupiah((int) $invoice->total_amount),
            'Sudah Dibayar: ' . $this->rupiah((int) $invoice->paid_amount),
            'Sisa: ' . $this->rupiah(max(0, (int) $invoice->total_amount - (int) $invoice->paid_amount)),
            '',
            'Pembayaran:',
        ]);

        if ($invoice->payments->isEmpty()) {
            $lines[] = '- Belum ada pembayaran';
        } else {
            foreach ($invoice->payments as $payment) {
                $account = $payment->paymentAccount
                    ? trim($payment->paymentAccount->nama_bank . ' ' . $payment->paymentAccount->nomor_rekening)
                    : '-';
                $lines[] = '- ' . $payment->paid_at?->format('d M Y') . ' | ' . $account . ' | ' . $this->rupiah((int) $payment->amount);
            }
        }

        return $this->buildSimplePdf($lines);
    }

    private function rupiah(int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    private function buildSimplePdf(array $lines): string
    {
        $content = "BT\n/F1 12 Tf\n50 790 Td\n";
        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $content .= "0 -18 Td\n";
            }
            $content .= '(' . $this->escape($line) . ") Tj\n";
        }
        $content .= "ET\n";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
