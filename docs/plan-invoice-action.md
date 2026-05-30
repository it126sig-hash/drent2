 # Plan: Invoice Tab — Aksi Column + Clickable Booking
 
  **Status:** belum applied. Tool edit mati di sesi sebelumnya.
  **Target file:** `frontend/src/views/finance/ReceivableListView.vue`
  **Konteks:** lanjutan dari fitur "rewire tombol Tambah pembayaran → invoice page" (sudah merged: BookingController 
  + BookingResource + BookingDetailView + ReceivableListView onMounted query handler).
 
  ## Goal
 
  Tab Invoice di `ReceivableListView`:
  1. Tambah kolom **Aksi** mirip tab Piutang (Bayar / Update / Send / View / PDF).
  2. Kolom **Booking** tiap entry clickable → `/bookings/{id}`.
 
  ## Data Shape Reminder
 
  Row di tab Invoice = invoice langsung (bukan receivable wrapper). Akses pakai:
  - `data.id`, `data.status`, `data.remaining_amount`, `data.sent_at`, `data.sent_by_name`
  - `data.bookings[*].id`, `data.bookings[*].kode_booking`, `data.bookings[*].customer_name`
 
  Beda dari Piutang (`data.invoice.id`, dst).
 
  Helper sudah ada di script: `canRefreshInvoice`, `openRefreshInvoiceDialog`, `openPaymentDialog`, `sendInvoice`,   
  `getInvoicePublicUrl`, `openInvoiceView`, `openInvoicePdf`, `invoiceSeverity`, `hasInvoiceChange`,
  `invoiceChangeLabel`, `formatDateTime`. Style `link-button`, `table-actions`, `action-pill-group`, `action-btn`    
  sudah ada di `<style scoped>`.
 
  ## Patch
 
  Cari blok ini (sekitar line 805–820, di dalam `<DataTable :value="invoices" ...>`):
 
  \`\`\`vue
            <Column header="Invoice" style="min-width: 13rem">
              <template #body="{ data }">
                <div class="font-bold">{{ data.invoice_number }}</div>
                <Tag :value="data.status" :severity="invoiceSeverity(data.status)" class="mt-2" />
                <Tag v-if="hasInvoiceChange(data)" :value="invoiceChangeLabel(data)" severity="warn" class="mt-2" /> 
              </template>
            </Column>
            <Column header="Booking" style="min-width: 16rem">
              <template #body="{ data }">
                <div class="booking-list">
                  <span v-for="booking in data.bookings" :key="booking.id">
                    {{ booking.kode_booking }} - {{ booking.customer_name || '-' }}
                  </span>
                </div>
              </template>
            </Column>
  \`\`\`
 
  Ganti dengan:
 
  \`\`\`vue
            <Column header="Aksi" style="min-width: 15rem">
              <template #body="{ data }">
                <div class="table-actions">
                  <button v-if="canRefreshInvoice(data)" class="btn-pill btn-primary btn-pill-compact"
                    :disabled="actionLoading" @click="openRefreshInvoiceDialog(data)">
                    <i class="pi pi-refresh"></i>
                    Update Invoice
                  </button>
                  <button v-else class="btn-pill btn-primary btn-pill-compact"
                    :disabled="(data.remaining_amount ?? 0) <= 0 || data.status === 'void'"
                    @click="openPaymentDialog(data)">
                    <i class="pi pi-wallet"></i>
                    Bayar Invoice
                  </button>
                  <span class="action-pill-group">
                    <button class="action-btn" :disabled="actionLoading" title="Kirim invoice"
                      @click="sendInvoice(data.id)">
                      <i class="pi pi-send"></i>
                    </button>
                    <button class="action-btn" :disabled="!getInvoicePublicUrl(data)" title="Lihat invoice"
                      @click="openInvoiceView(data)">
                      <i class="pi pi-eye"></i>
                    </button>
                    <button class="action-btn" :disabled="actionLoading" title="Unduh PDF"
                      @click="openInvoicePdf(data)">
                      <i class="pi pi-file-pdf"></i>
                    </button>
                  </span>
                  <span v-if="data.sent_at" class="text-xs mt-1 text-secondary">
                    {{ formatDateTime(data.sent_at) }}
                    <span v-if="data.sent_by_name">({{ data.sent_by_name }})</span>
                  </span>
                </div>
              </template>
            </Column>
            <Column header="Invoice" style="min-width: 13rem">
              <template #body="{ data }">
                <div class="font-bold">{{ data.invoice_number }}</div>
                <Tag :value="data.status" :severity="invoiceSeverity(data.status)" class="mt-2" />
                <Tag v-if="hasInvoiceChange(data)" :value="invoiceChangeLabel(data)" severity="warn" class="mt-2" /> 
              </template>
            </Column>
            <Column header="Booking" style="min-width: 16rem">
              <template #body="{ data }">
                <div class="booking-list">
                  <button v-for="booking in data.bookings" :key="booking.id" class="link-button text-xs flex"        
                    @click="router.push(`/bookings/${booking.id}`)"
                    :title="`Buka detail booking ${booking.kode_booking}`">
                    {{ booking.kode_booking }} - {{ booking.customer_name || '-' }}
                  </button>
                </div>
              </template>
            </Column>
  \`\`\`
 
  ## Verifikasi
 
  1. Vite hot-reload pickup file.
  2. Buka tab Invoice → Aksi column muncul paling kiri.
  3. Invoice status `generated`/`partial_paid` + ada perubahan → "Update Invoice" aktif. Selain itu → "Bayar Invoice"
  (disabled bila `remaining_amount <= 0` atau `status==='void'`).
  4. Klik Send → `markSent` jalan, link disalin clipboard.
  5. Klik Eye → buka public URL di tab baru.
  6. Klik PDF → `openPdf` → tab baru.
  7. Klik booking entry di kolom Booking → navigate ke `/bookings/{id}`.
 
  ## Edge
 
  - Invoice tanpa `public_path`/`public_url` → eye disabled.
  - Invoice belum pernah sent → tidak tampil baris `sent_at`.
  - Multi-booking invoice (gabungan) → tiap booking jadi tombol terpisah, klik salah satu navigate ke booking itu.   
 
  ## Tidak Berubah
 
  - API endpoint, composable `useReceivable`, response shape backend.
  - Tab Piutang & Riwayat Pembayaran tetap.
 
  ## Cara Apply Cepat (sesi baru)
 
  \`\`\`bash
  # Buka file, cari header "Invoice" di dalam DataTable invoices,
  # ganti 2 Column itu dengan 3 Column versi baru.
  \`\`\`
 