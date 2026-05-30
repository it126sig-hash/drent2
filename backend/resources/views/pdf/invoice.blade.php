<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1f2937;
            background: #ffffff;
        }

        .page {
            position: relative;
            width: 100%;
            min-height: 297mm;
            padding-bottom: 40px;
        }

        /* TOP BAR */
        .top-bar {
            background-color: #1f2937;
            color: #ffffff;
            padding: 32px 40px;
        }

        .top-bar-table {
            width: 100%;
            border-collapse: collapse;
        }

        .top-bar-table td {
            vertical-align: middle;
        }

        .brand-cell {
            text-align: left;
        }

        .brand-name {
            font-size: 22px;
            font-weight: 700;
            line-height: 1.1;
            letter-spacing: 0.5px;
        }

        .brand-tagline {
            font-size: 9px;
            color: #cbd5e1;
            letter-spacing: 1px;
            margin-top: 4px;
        }

        .invoice-title-cell {
            text-align: right;
        }

        .invoice-title {
            font-size: 32px;
            font-weight: 700;
            color: #E5534B;
            letter-spacing: 2px;
        }

        /* RED LINE */
        .red-line {
            height: 8px;
            background-color: #E5534B;
            line-height: 0;
            font-size: 0;
        }

        /* META BAR */
        .meta-bar {
            background-color: #272C3F;
            color: #ffffff;
            padding: 24px 40px;
        }

        .meta-bar-table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-bar-table td {
            vertical-align: top;
        }

        .billed-to-cell {
            width: 60%;
            padding-right: 20px;
        }

        .invoice-details-cell {
            width: 40%;
        }

        .billed-to-title {
            font-size: 14px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 6px;
        }

        .customer-name {
            font-size: 14px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 4px;
        }

        .customer-line {
            font-size: 11px;
            color: #cbd5e1;
            line-height: 1.5;
        }

        .invoice-detail-row {
            font-size: 12px;
            line-height: 1.7;
        }

        .invoice-detail-row .label {
            font-weight: 700;
            color: #ffffff;
        }

        .invoice-detail-row .value {
            color: #ffffff;
        }

        /* TABLE */
        .items-section {
            padding: 24px 40px 0 40px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table thead th {
            background-color: #EAF0EC;
            color: #1f2937;
            font-weight: 700;
            font-size: 12px;
            padding: 10px 8px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .items-table thead th.center {
            text-align: center;
        }

        .items-table thead th.right {
            text-align: right;
        }

        .items-table tbody td {
            padding: 12px 8px;
            font-size: 11px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .items-table tbody td.center {
            text-align: center;
        }

        .items-table tbody td.right {
            text-align: right;
        }

        .item-meta {
            color: #6b7280;
            font-size: 10px;
            margin-top: 2px;
            line-height: 1.4;
        }

        .mono {
            font-family: DejaVu Sans Mono, monospace;
        }

        /* BOTTOM SECTION */
        .bottom-section {
            padding: 28px 40px 0 40px;
        }

        .bottom-table {
            width: 100%;
            border-collapse: collapse;
        }

        .bottom-table > tbody > tr > td {
            vertical-align: top;
        }

        .bottom-left-cell {
            width: 60%;
            padding-right: 20px;
        }

        .bottom-right-cell {
            width: 40%;
            padding-left: 10px;
        }

        .thank-you {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .section-label {
            color: #6b7280;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .payment-history-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .payment-history-table td {
            padding: 6px 0;
            border-bottom: 1px solid #e5e7eb;
            font-size: 11px;
            vertical-align: top;
        }

        .payment-history-table td.amount {
            text-align: right;
            font-family: DejaVu Sans Mono, monospace;
            font-weight: 700;
            white-space: nowrap;
        }

        .payment-history-table .meta {
            color: #6b7280;
            font-size: 10px;
            margin-top: 1px;
        }

        .payment-title {
            font-size: 13px;
            font-weight: 700;
            margin-top: 4px;
            margin-bottom: 8px;
        }

        .accounts-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .accounts-table td {
            border: 1px solid #e5e7eb;
            background-color: #f9fafb;
            border-radius: 4px;
            padding: 8px;
            width: 50%;
            vertical-align: top;
            font-size: 11px;
        }

        .account-bank {
            font-weight: 700;
            color: #1f2937;
            font-size: 11px;
        }

        .account-number {
            font-family: DejaVu Sans Mono, monospace;
            font-weight: 700;
        }

        .account-name {
            color: #6b7280;
            margin-top: 2px;
        }

        .terms-title {
            font-size: 13px;
            font-weight: 700;
            margin-top: 8px;
            margin-bottom: 6px;
        }

        .terms-text {
            font-size: 10px;
            color: #6b7280;
            line-height: 1.5;
            margin: 0;
        }

        /* TOTALS */
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 7px 0;
            font-size: 12px;
            font-weight: 600;
            vertical-align: middle;
        }

        .totals-table td.right {
            text-align: right;
            font-family: DejaVu Sans Mono, monospace;
        }

        .totals-table tr.grand-total td {
            border-top: 1px solid #1f2937;
            border-bottom: 1px solid #1f2937;
            font-size: 14px;
            padding: 10px 0;
        }

        .status-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .status-pill.success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-pill.info {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-pill.warn {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-pill.danger {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .signature-section {
            margin-top: 50px;
            text-align: center;
            width: 60%;
            margin-left: auto;
        }

        .signature-line {
            border-bottom: 1px solid #1f2937;
            height: 1px;
            margin-bottom: 6px;
        }

        .signature-text {
            font-size: 11px;
            font-weight: 600;
        }

        .empty-text {
            color: #6b7280;
            font-size: 11px;
            font-style: italic;
        }

        /* FOOTER BAND */
        .footer-band {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 24px;
            background-color: #272C3F;
        }
    </style>
</head>
<body>
    <div class="page">
        {{-- TOP HEADER --}}
        <div class="top-bar">
            <table class="top-bar-table">
                <tr>
                    <td class="brand-cell">
                        <div class="brand-name">{{ $branchName }}</div>
                        <div class="brand-tagline">CAR RENTAL SYSTEM</div>
                    </td>
                    <td class="invoice-title-cell">
                        <div class="invoice-title">INVOICE</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="red-line">&nbsp;</div>

        {{-- META BAR --}}
        <div class="meta-bar">
            <table class="meta-bar-table">
                <tr>
                    <td class="billed-to-cell">
                        <div class="billed-to-title">Billed to:</div>
                        <div class="customer-name">{{ $customerName }}</div>
                        @foreach ($customerAddressLines as $line)
                            <div class="customer-line">{{ $line }}</div>
                        @endforeach
                        @foreach ($customerContactLines as $line)
                            <div class="customer-line">{{ $line }}</div>
                        @endforeach
                        @if (empty($customerAddressLines) && empty($customerContactLines))
                            <div class="customer-line">Terima kasih telah menggunakan layanan penyewaan kami.</div>
                        @endif
                    </td>
                    <td class="invoice-details-cell">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr class="invoice-detail-row">
                                <td class="label">Invoice#</td>
                                <td class="value mono" style="text-align: right;">{{ $invoice->invoice_number }}</td>
                            </tr>
                            <tr class="invoice-detail-row">
                                <td class="label">Date:</td>
                                <td class="value mono" style="text-align: right;">{{ $invoiceDate }}</td>
                            </tr>
                            <tr class="invoice-detail-row">
                                <td class="label">Due Date:</td>
                                <td class="value mono" style="text-align: right;">{{ $dueDate }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        {{-- ITEMS TABLE --}}
        <div class="items-section">
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="center" style="width: 8%;">SL.</th>
                        <th style="width: 50%;">Item Description</th>
                        <th class="right" style="width: 16%;">Price</th>
                        <th class="center" style="width: 10%;">Qty.</th>
                        <th class="right" style="width: 16%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $index => $item)
                        <tr>
                            <td class="center">{{ $index + 1 }}</td>
                            <td>
                                <div>
                                    <strong>{{ $item['description'] ?: ($item['booking_code'] ?? 'Rental Service') }}:</strong>
                                    @if (!empty($item['vehicle_name']) || !empty($item['vehicle_plate']))
                                        <span style="font-weight: 600;">{{ $item['vehicle_name'] ?: 'Rental Service' }}</span>
                                        @if (!empty($item['vehicle_plate']))
                                            <span class="mono">({{ $item['vehicle_plate'] }})</span>
                                        @endif
                                    @endif
                                </div>
                                @if (!empty($item['label']))
                                    <div class="item-meta">
                                        {{ $item['label'] }}@if (!empty($item['note'])) : {{ $item['note'] }}@endif
                                    </div>
                                @endif
                                @if (!empty($item['rental_start_date']) || !empty($item['rental_end_date']))
                                    <div class="item-meta">
                                        {{ $formatDate($item['rental_start_date'] ?? null) }} - {{ $formatDate($item['rental_end_date'] ?? null) }}
                                    </div>
                                @endif
                            </td>
                            <td class="right mono">{{ $formatCurrency($item['price'] ?? $item['amount'] ?? 0) }}</td>
                            <td class="center mono">{{ $item['qty'] ?? 1 }}</td>
                            <td class="right mono"><strong>{{ $formatCurrency($item['amount'] ?? 0) }}</strong></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="center empty-text">Belum ada detail item invoice.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- BOTTOM SECTION --}}
        <div class="bottom-section">
            <table class="bottom-table">
                <tr>
                    {{-- LEFT --}}
                    <td class="bottom-left-cell">
                        <div class="thank-you">Thank you for your business</div>

                        {{-- Payment History --}}
                        <div class="section-label">History Payment</div>
                        @if (count($payments))
                            <table class="payment-history-table">
                                @foreach ($payments as $payment)
                                    <tr>
                                        <td>
                                            <strong>{{ $formatDate($payment['paid_at'] ?? null) }}</strong>
                                            <div class="meta">{{ $payment['payment_account_name'] ?? '-' }}</div>
                                        </td>
                                        <td class="amount mono">{{ $formatCurrency($payment['amount'] ?? 0) }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @else
                            <div class="empty-text" style="margin-bottom: 16px;">Belum ada pembayaran.</div>
                        @endif

                        <div class="payment-title">Payment Info:</div>
                        <div class="section-label">Nomor Rekening</div>
                        @if (count($paymentAccounts))
                            <table class="accounts-table">
                                @php $rows = array_chunk($paymentAccounts, 2); @endphp
                                @foreach ($rows as $row)
                                    <tr>
                                        @foreach ($row as $account)
                                            <td>
                                                <div class="account-bank">{{ $account['nama_bank'] }}: <span class="account-number">{{ $account['nomor_rekening'] }}</span></div>
                                                <div class="account-name">{{ $account['atas_nama'] }}</div>
                                            </td>
                                        @endforeach
                                        @if (count($row) === 1)
                                            <td style="border: none; background: transparent;"></td>
                                        @endif
                                    </tr>
                                @endforeach
                            </table>
                        @else
                            <div class="empty-text" style="margin-bottom: 16px;">Tidak ada informasi rekening pembayaran.</div>
                        @endif

                        <div class="terms-title">Terms &amp; Conditions</div>
                        <p class="terms-text">Harap lakukan pembayaran sebelum tanggal jatuh tempo. Keterlambatan dapat dikenakan denda sesuai dengan ketentuan penyewaan. Terima kasih telah mempercayai DRENT.</p>
                    </td>

                    {{-- RIGHT --}}
                    <td class="bottom-right-cell">
                        <table class="totals-table">
                            <tr>
                                <td>Sub Total:</td>
                                <td class="right">{{ $formatCurrency($invoice->total_amount) }}</td>
                            </tr>
                            <tr>
                                <td>Paid:</td>
                                <td class="right">{{ $formatCurrency($paidAmount) }}</td>
                            </tr>
                            <tr>
                                <td>Status:</td>
                                <td class="right">
                                    <span class="status-pill {{ $statusSeverity }}">{{ strtoupper(str_replace('_', ' ', $invoice->status)) }}</span>
                                </td>
                            </tr>
                            <tr class="grand-total">
                                <td>Remaining:</td>
                                <td class="right">{{ $formatCurrency($remainingAmount) }}</td>
                            </tr>
                        </table>

                        <div class="signature-section">
                            <div class="signature-line">&nbsp;</div>
                            <div class="signature-text">Authorised Sign</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="footer-band">&nbsp;</div>
</body>
</html>
