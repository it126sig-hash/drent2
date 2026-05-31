import { format } from 'date-fns'

const fmt = (v) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v || 0)

const fmtDate = (v) => {
  if (!v) return '-'
  try { return format(new Date(v), 'dd MMM yyyy') } catch { return '-' }
}

const fmtDateTime = (v) => {
  if (!v) return '-'
  try { return format(new Date(v), 'dd MMM yyyy HH:mm') } catch { return '-' }
}

const esc = (s) => String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')

/**
 * receipt shape:
 * {
 *   branch: { name, address, phone, email, logo_url },
 *   customer_name,
 *   customer_address,
 *   customer_phone,
 *   receipt_number,
 *   receipt_date,          // ISO string – date payment was made
 *   payment_type_label,    // "DP / Uang Muka" | "Cicilan" | "Pelunasan" | etc.
 *   payment_account_name,  // "BCA - 1234567890"
 *   note,
 *   items: [{ description, amount, note? }],
 *   vehicle_info,          // "Toyota Avanza (B 1234 XY)"  — optional
 *   rental_period: { start, end }  — optional
 *   summary: { total_tagihan, total_paid_before, this_payment, remaining_after },
 *   created_by_name,
 *   generated_at,          // timestamp this receipt was generated
 * }
 */
export function printPaymentReceipt(receipt) {
  const branch = receipt.branch || {}

  const contactItems = [
    branch.address ? `<span class="contact-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>${esc(branch.address)}</span>` : '',
    branch.phone ? `<span class="contact-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 8.81 19.79 19.79 0 01.07 2.18 2 2 0 012.03 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 14.92v2z"/></svg>${esc(branch.phone)}</span>` : '',
    branch.email ? `<span class="contact-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,12 2,6"/></svg>${esc(branch.email)}</span>` : '',
  ].filter(Boolean).join('')

  const logoHtml = branch.logo_url
    ? `<img src="${esc(branch.logo_url)}" alt="${esc(branch.name)}" class="brand-logo" />`
    : `<div class="brand-logo-placeholder"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h11a2 2 0 012 2v3"/><rect x="9" y="11" width="14" height="10" rx="2"/><circle cx="12" cy="16" r="1"/></svg></div>`

  const itemsHtml = (receipt.items || []).map((item, i) => {
    const vehicleMeta = receipt.vehicle_info ? `<div class="item-meta">${esc(receipt.vehicle_info)}</div>` : ''
    const periodMeta = receipt.rental_period
      ? `<div class="item-meta">${fmtDate(receipt.rental_period.start)} &ndash; ${fmtDate(receipt.rental_period.end)}</div>`
      : ''
    const noteMeta = item.note ? `<div class="item-meta">${esc(item.note)}</div>` : ''
    return `
      <div class="table-row">
        <div class="col-sl">${i + 1}</div>
        <div class="col-desc">
          <div class="item-desc-main">${esc(item.description)}</div>
          ${vehicleMeta}${periodMeta}${noteMeta}
        </div>
        <div class="col-total">${fmt(item.amount)}</div>
      </div>`
  }).join('')

  const thisPayment = receipt.summary?.this_payment ?? receipt.items?.[0]?.amount
  const summaryHtml = `
    <div class="total-row grand-payment">
      <span>Jumlah Diterima</span>
      <span>${fmt(thisPayment)}</span>
    </div>`

  const noteHtml = receipt.note ? `
    <div class="receipt-note">
      <div class="section-label">Catatan</div>
      <div>${esc(receipt.note)}</div>
    </div>` : ''

  const paymentTypeHtml = receipt.payment_type_label
    ? `<div class="detail-row"><span>Jenis Pembayaran</span><span>${esc(receipt.payment_type_label)}</span></div>`
    : ''

  const paymentAccountHtml = receipt.payment_account_name
    ? `<div class="detail-row"><span>Via</span><span>${esc(receipt.payment_account_name)}</span></div>`
    : ''

  const html = `<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>${esc(receipt.receipt_number || 'Kwitansi')}</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #f0f2f5;
      color: #1a1f2e;
      padding: 28px 20px 40px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .action-bar {
      width: 100%;
      max-width: 210mm;
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-bottom: 14px;
    }

    .print-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 18px;
      border: none;
      border-radius: 20px;
      background: #1a1f2e;
      color: #fff;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
      letter-spacing: .3px;
    }

    .receipt-container {
      width: 100%;
      max-width: 210mm;
      min-height: 297mm;
      background: #fff;
      box-shadow: 0 8px 32px rgba(0,0,0,.12);
      border-radius: 4px;
      overflow: hidden;
      position: relative;
      padding-bottom: 48px;
    }

    /* ── TOP BAR ── */
    .receipt-top-bar {
      background: #1a1f2e;
      color: #fff;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding: 40px;
      gap: 24px;
    }

    .brand-info { display: flex; flex-direction: column; gap: 16px; flex: 1; min-width: 0; }
    .brand-header { display: flex; align-items: center; gap: 16px; }

    .brand-logo {
      width: 56px; height: 56px;
      object-fit: contain;
      background: #fff;
      border-radius: 4px;
      padding: 6px;
      flex-shrink: 0;
    }

    .brand-logo-placeholder {
      width: 56px; height: 56px;
      background: rgba(255,255,255,.15);
      border-radius: 4px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      color: rgba(255,255,255,.7);
    }
    .brand-logo-placeholder svg { width: 28px; height: 28px; }

    .brand-name { font-size: 24px; font-weight: 700; line-height: 1.2; }
    .brand-tagline { font-size: 10px; color: #9ca3b0; letter-spacing: 1px; margin-top: 2px; }

    .brand-contact {
      display: flex;
      flex-wrap: wrap;
      gap: 6px 14px;
      font-size: 11px;
      color: #9ca3b0;
    }

    .contact-item {
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
    .contact-item svg { width: 11px; height: 11px; flex-shrink: 0; }

    .receipt-title-block {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 6px;
      flex-shrink: 0;
    }

    .receipt-title {
      font-size: 36px;
      font-weight: 700;
      color: #E5534B;
      letter-spacing: 2px;
      line-height: 1;
    }

    .receipt-title-meta {
      font-size: 11px;
      color: #9ca3b0;
      text-align: right;
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    /* ── RED LINE ── */
    .red-line { height: 8px; background: #E5534B; }

    /* ── META BAR ── */
    .receipt-meta-bar {
      background: #272C3F;
      color: #fff;
      padding: 30px 40px;
      display: flex;
      justify-content: space-between;
      gap: 24px;
    }

    .receipt-to { font-size: 12px; color: #9ca3b0; max-width: 360px; }
    .receipt-to-label { font-size: 16px; font-weight: 600; color: #fff; margin-bottom: 8px; }
    .customer-name { font-size: 16px; font-weight: 600; color: #fff; margin-bottom: 4px; }
    .customer-sub { font-size: 12px; color: #9ca3b0; line-height: 1.4; margin-top: 2px; }

    .receipt-details {
      font-size: 13px;
      color: #fff;
      display: flex;
      flex-direction: column;
      gap: 8px;
      min-width: 220px;
    }

    .detail-row {
      display: flex;
      justify-content: space-between;
      gap: 16px;
    }
    .detail-row span:first-child { font-weight: 600; color: #fff; }
    .detail-row span:last-child { font-family: 'Courier New', monospace; }

    /* ── TABLE ── */
    .receipt-table { padding: 0 40px; margin-top: 30px; }

    .table-header {
      display: flex;
      background: #EAF0EC;
      padding: 12px 20px;
      font-weight: 700;
      font-size: 14px;
      color: #1a1f2e;
    }

    .table-row {
      display: flex;
      padding: 16px 20px;
      border-bottom: 1px solid #e8eaed;
      font-size: 13px;
      align-items: flex-start;
    }

    .col-sl { width: 10%; text-align: center; flex-shrink: 0; padding-top: 1px; }
    .col-desc { width: 75%; }
    .col-total { width: 15%; text-align: right; font-family: 'Courier New', monospace; font-weight: 700; }

    .item-desc-main { font-weight: 600; }
    .item-meta { color: #6b7280; font-size: 11px; margin-top: 3px; line-height: 1.4; }

    /* ── BOTTOM ── */
    .receipt-bottom {
      display: flex;
      justify-content: space-between;
      padding: 40px;
      gap: 40px;
    }

    .bottom-left { flex: 1; display: flex; flex-direction: column; gap: 20px; }

    .thank-you { font-size: 14px; font-weight: 700; }

    .receipt-note { font-size: 12px; color: #4b5563; }
    .section-label {
      font-size: 10px;
      font-weight: 800;
      letter-spacing: .4px;
      text-transform: uppercase;
      color: #9ca3af;
      margin-bottom: 6px;
    }

    .bottom-right { width: 260px; display: flex; flex-direction: column; justify-content: space-between; gap: 40px; }

    .totals-section { width: 100%; display: flex; flex-direction: column; gap: 0; }

    .total-row {
      display: flex;
      justify-content: space-between;
      padding: 8px 0;
      font-size: 13px;
      font-weight: 600;
      border-bottom: 1px solid #e8eaed;
    }
    .total-row:last-child { border-bottom: none; }
    .total-row span:last-child { font-family: 'Courier New', monospace; }

    .total-row.grand-payment {
      margin-top: 8px;
      background: #1a1f2e;
      color: #fff;
      padding: 12px;
      border-radius: 4px;
      border: none;
      font-size: 14px;
    }
    .total-row.grand-payment span:last-child { color: #fff; }

    .total-row.remaining {
      color: #E5534B;
      font-size: 12px;
      padding-top: 6px;
    }

    .signature-section {
      text-align: right;
      width: 150px;
      align-self: flex-end;
    }
    .signature-img {
      width: 150px;
      height: 60px;
      object-fit: contain;
      margin-bottom: 4px;
    }
    .signature-line {
      border-bottom: 1px solid #1a1f2e;
      width: 100%;
      margin-bottom: 8px;
    }
    .signature-text { font-size: 13px; font-weight: 700; text-align: center; word-break: break-word; }
    .signature-caption {
      font-size: 10px;
      font-weight: 500;
      text-align: center;
      color: #6b7280;
      letter-spacing: .4px;
      text-transform: uppercase;
      margin-top: 2px;
    }

    /* ── FOOTER BAND ── */
    .receipt-footer-band {
      position: absolute;
      bottom: 0; left: 0; right: 0;
      height: 24px;
      background: #272C3F;
    }

    /* ── GENERATED ── */
    .generated-note {
      font-size: 10px;
      color: #9ca3af;
      text-align: center;
      padding: 12px 40px 0;
    }

    /* ── PRINT ── */
    @page { size: A4; margin: 0; }

    @media print {
      body {
        background: #fff;
        padding: 0;
        display: block;
      }
      .action-bar { display: none; }
      .receipt-container {
        width: 210mm;
        min-height: 297mm;
        max-width: none;
        border-radius: 0;
        box-shadow: none;
      }
    }
  </style>
</head>
<body>
  <div class="action-bar">
    <button class="print-btn" onclick="window.print()">
      &#128438; Cetak / Simpan PDF
    </button>
  </div>

  <div class="receipt-container">
    <!-- ── Header ── -->
    <div class="receipt-top-bar">
      <div class="brand-info">
        <div class="brand-header">
          ${logoHtml}
          <div>
            <div class="brand-name">${esc(branch.name || 'DRENT')}</div>
            <div class="brand-tagline">CAR RENTAL SYSTEM</div>
          </div>
        </div>
        ${contactItems ? `<div class="brand-contact">${contactItems}</div>` : ''}
      </div>
      <div class="receipt-title-block">
        <div class="receipt-title">KWITANSI</div>
        <div class="receipt-title-meta">
          <div>${esc(receipt.receipt_number || '-')}</div>
          <div>${fmtDateTime(receipt.generated_at || new Date().toISOString())}</div>
        </div>
      </div>
    </div>

    <div class="red-line"></div>

    <!-- ── Meta bar ── -->
    <div class="receipt-meta-bar">
      <div class="receipt-to">
        <div class="receipt-to-label">Telah diterima dari:</div>
        <div class="customer-name">${esc(receipt.customer_name || 'Pelanggan')}</div>
        ${receipt.customer_address ? `<div class="customer-sub">${esc(receipt.customer_address)}</div>` : ''}
        ${receipt.customer_phone ? `<div class="customer-sub">${esc(receipt.customer_phone)}</div>` : ''}
      </div>
      <div class="receipt-details">
        <div class="detail-row"><span>No. Kwitansi</span><span>${esc(receipt.receipt_number || '-')}</span></div>
        <div class="detail-row"><span>Tanggal</span><span>${fmtDate(receipt.receipt_date)}</span></div>
        ${paymentTypeHtml}
        ${paymentAccountHtml}
      </div>
    </div>

    <!-- ── Table ── -->
    <div class="receipt-table">
      <div class="table-header">
        <div class="col-sl">SL.</div>
        <div class="col-desc">Keterangan</div>
        <div class="col-total">Jumlah</div>
      </div>
      ${itemsHtml}
    </div>

    <!-- ── Bottom ── -->
    <div class="receipt-bottom">
      <div class="bottom-left">
        <div class="thank-you">Terima kasih atas kepercayaan Anda.</div>
        ${noteHtml}
      </div>
      <div class="bottom-right">
        <div class="totals-section">
          ${summaryHtml}
        </div>
        <div class="signature-section">
          ${receipt.signature_url ? `<img src="${esc(receipt.signature_url)}" class="signature-img" />` : `<div class="signature-line"></div>`}
          <div class="signature-text">${esc(receipt.created_by_name || 'Admin')}</div>
          <div class="signature-caption">Yang Menerima</div>
        </div>
      </div>
    </div>

    <div class="generated-note">
      Dicetak pada ${fmtDateTime(new Date().toISOString())}
    </div>

    <div class="receipt-footer-band"></div>
  </div>
</body>
</html>`

  const win = window.open('', '_blank', 'width=960,height=760')
  if (!win) return
  win.document.write(html)
  win.document.close()
}

/**
 * Build receipt data from BookingDetailView payment context.
 *
 * @param {object} booking  – full booking object from BookingDetailView
 * @param {object} payment  – single payment from booking.payments
 * @param {array}  accounts – payment accounts list (from usePaymentAccount)
 * @param {object} branch   – branch from authStore.branch
 */
export function buildReceiptFromBookingPayment(booking, payment, accounts, branch) {
  const account = (accounts || []).find(a => a.id === payment.payment_account_id)
  const accountName = account
    ? `${account.nama_bank} — ${account.nomor_rekening}`
    : null

  const typeLabels = { dp: 'DP / Uang Muka', cicilan: 'Cicilan', pelunasan: 'Pelunasan' }

  // Cumulative paid before THIS payment (sorted by paid_at then id)
  const activePayments = (booking.payments || []).filter(p => p.status !== 'voided')
  const sortKey = (p) => `${p.paid_at || ''}__${String(p.id).padStart(10, '0')}`
  const sorted = [...activePayments].sort((a, b) => sortKey(a).localeCompare(sortKey(b)))
  const thisIdx = sorted.findIndex(p => p.id === payment.id)
  const paidBefore = sorted.slice(0, thisIdx).reduce((s, p) => s + (p.amount || 0), 0)

  const primaryDetail = (booking.booking_details || []).find(d => d.detail_type === 'initial') || booking.booking_details?.[0]
  const unit = primaryDetail?.unit
  const vehicleInfo = unit
    ? `${[unit.merk, unit.tipe].filter(Boolean).join(' ')} (${unit.no_polisi || '-'})`.trim()
    : null

  const totalTagihan = booking.total_tagihan ?? null
  const thisAmount = payment.amount || 0
  const remainingAfter = totalTagihan != null ? totalTagihan - paidBefore - thisAmount : null

  return {
    branch,
    customer_name: booking.customer?.nama,
    customer_address: booking.customer?.kota || null,
    customer_phone: null,
    receipt_number: `KWT-${booking.kode_booking}-${payment.id}`,
    receipt_date: payment.paid_at || payment.created_at,
    generated_at: new Date().toISOString(),
    payment_type_label: typeLabels[payment.payment_type] || payment.payment_type || null,
    payment_account_name: accountName,
    note: payment.catatan || null,
    items: [{
      description: `Pembayaran ${typeLabels[payment.payment_type] || payment.payment_type || ''} – Booking ${booking.kode_booking}`,
      amount: thisAmount,
      note: payment.catatan || null,
    }],
    vehicle_info: vehicleInfo,
    rental_period: primaryDetail
      ? { start: primaryDetail.tgl_sewa, end: primaryDetail.tgl_kembali }
      : null,
    summary: {
      total_tagihan: totalTagihan,
      total_paid_before: paidBefore,
      this_payment: thisAmount,
      remaining_after: remainingAfter,
    },
    created_by_name: payment.creator?.name || null,
  }
}

/**
 * Build receipt data from ReceivableListView payment history entry.
 *
 * @param {object} payment – entry from paymentHistory.latest
 * @param {object} branch  – branch from authStore.branch
 */
export function buildReceiptFromPaymentHistory(payment, branch) {
  const transactionCodes = (payment.transaction_codes || []).filter(Boolean).join(', ')
  const customerNames = (payment.customer_names || []).filter(Boolean).join(', ')

  return {
    branch,
    customer_name: customerNames || '-',
    customer_address: null,
    customer_phone: null,
    receipt_number: payment.reference_number || `KWT-${Date.now()}`,
    receipt_date: payment.paid_at,
    generated_at: new Date().toISOString(),
    payment_type_label: payment.source_label || null,
    payment_account_name: payment.payment_account_name || null,
    note: null,
    items: [{
      description: `${payment.source_label || 'Pembayaran'}${transactionCodes ? ` – ${transactionCodes}` : ''}`,
      amount: payment.amount || 0,
    }],
    vehicle_info: null,
    rental_period: null,
    summary: null,
    created_by_name: payment.created_by_name || null,
  }
}
