<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { format } from 'date-fns'
import { useToast } from 'primevue/usetoast'
import { useBooking } from '../../composables/useBooking'
import { usePhysicalCheck } from '../../composables/usePhysicalCheck'
import SignaturePad from '../../components/physical-checks/SignaturePad.vue'
import BookingStatusBadge from '../../components/bookings/BookingStatusBadge.vue'
import fuelGaugeImage from '../../assets/fuel-gauge.svg'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Skeleton from 'primevue/skeleton'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { fetchOne } = useBooking()
const { loading, items, fetchItems, fetchByBooking, requestCheck, store } = usePhysicalCheck()

const booking = ref(null)
const existingCheck = ref(null)
const kmOdometer = ref(null)
const fuelLevel = ref('')
const fuelMarker = ref(null)
const generalNotes = ref('')
const inspectorName = ref('')
const customerName = ref('')
const inspectorSignature = ref('')
const customerSignature = ref('')
const checklistRows = ref([])

const sections = ref([
  { key: 'front', label: 'Tampak depan', notes: '', photos: [] },
  { key: 'left', label: 'Samping kiri', notes: '', photos: [] },
  { key: 'right', label: 'Samping kanan', notes: '', photos: [] },
  { key: 'rear', label: 'Tampak belakang', notes: '', photos: [] },
  { key: 'interior', label: 'Interior', notes: '', photos: [] },
  { key: 'km', label: 'Foto KM terakhir', notes: '', photos: [] },
])

const photoRequiredSections = ['front', 'left', 'right', 'rear', 'interior', 'km']
const type = computed(() => route.params.type)
const bookingId = computed(() => route.params.bookingId)
const isReturn = computed(() => type.value === 'return')
const title = computed(() => isReturn.value ? 'Cek Fisik Pengembalian' : 'Cek Fisik Keberangkatan')
const readonly = computed(() => ['completed', 'skipped'].includes(existingCheck.value?.status))
const canSubmit = computed(() => !readonly.value && eligibility.value.allowed)

const activeDetail = computed(() => {
  const details = booking.value?.booking_details || []
  return details.find(detail => detail.status === 'aktif')
    || details.find(detail => detail.detail_type === 'initial')
    || details.find(detail => detail.status === 'draft')
    || details[details.length - 1]
})

const vehicleTitle = computed(() => {
  const unit = activeDetail.value?.unit
  if (unit) return [unit.merk, unit.tipe].filter(Boolean).join(' ') || 'Unit tanpa nama'
  return activeDetail.value?.unit_placeholder || 'Belum ditentukan'
})

const statusText = (status) => ({
  requested: 'Diminta',
  completed: 'Selesai',
  skipped: 'Dilewati',
  not_requested: 'Belum diminta'
}[status] || status || 'Belum diminta')

const statusSeverity = (status) => ({
  requested: 'warning',
  completed: 'success',
  skipped: 'danger',
  not_requested: 'secondary'
}[status] || 'info')

const formatDateTime = (value) => {
  if (!value) return '-'
  return format(new Date(value), 'dd MMM yyyy HH:mm')
}

const dayOnly = (value) => {
  const date = new Date(value)
  return new Date(date.getFullYear(), date.getMonth(), date.getDate())
}

const addDays = (date, days) => {
  const copy = new Date(date)
  copy.setDate(copy.getDate() + days)
  return copy
}

const eligibility = computed(() => {
  if (!booking.value || !activeDetail.value) {
    return { allowed: false, reason: 'Detail kendaraan belum tersedia.' }
  }

  if (!isReturn.value && booking.value.status !== 'waiting_list') {
    return { allowed: false, reason: 'Cek keberangkatan hanya untuk booking Waiting List.' }
  }

  if (isReturn.value && booking.value.status !== 'rental_unit') {
    return { allowed: false, reason: 'Cek pengembalian hanya untuk booking Rental Unit.' }
  }

  const targetDate = isReturn.value ? activeDetail.value.tgl_kembali : activeDetail.value.tgl_sewa
  if (!targetDate) {
    return { allowed: false, reason: 'Tanggal sewa/kembali belum tersedia.' }
  }

  const target = dayOnly(targetDate)
  const start = isReturn.value ? target : addDays(target, -1)
  const end = isReturn.value ? addDays(target, 1) : target
  const today = dayOnly(new Date())

  if (today < start) {
    return {
      allowed: false,
      reason: isReturn.value
        ? 'Cek pengembalian baru bisa dilakukan pada tanggal kembali.'
        : 'Cek keberangkatan baru bisa dilakukan H-1 atau hari H tanggal sewa.'
    }
  }

  if (today > end) {
    return {
      allowed: false,
      reason: isReturn.value
        ? 'Cek pengembalian hanya bisa dilakukan tanggal kembali sampai H+1.'
        : 'Window cek keberangkatan sudah lewat.'
    }
  }

  return { allowed: true, reason: null }
})

const loadData = async () => {
  booking.value = await fetchOne(bookingId.value)
  await fetchItems()
  existingCheck.value = await fetchByBooking(bookingId.value, type.value)

  if (!existingCheck.value) {
    try {
      existingCheck.value = await requestCheck(bookingId.value, type.value)
    } catch (err) {
      existingCheck.value = null
    }
  }

  initializeChecklist()
  hydrateExistingCheck()
}

const initializeChecklist = () => {
  checklistRows.value = items.value.map(item => ({
    physical_check_item_id: item.id,
    item_label: item.name,
    is_present: true,
    notes: ''
  }))
}

const hydrateExistingCheck = () => {
  const check = existingCheck.value
  if (!check) return

  kmOdometer.value = check.km_odometer || null
  fuelLevel.value = check.fuel_level || ''
  fuelMarker.value = check.fuel_marker_x != null && check.fuel_marker_y != null
    ? { x: Number(check.fuel_marker_x), y: Number(check.fuel_marker_y) }
    : null
  generalNotes.value = check.notes || ''

  for (const section of sections.value) {
    const storedSection = check.sections?.find(item => item.section === section.key)
    section.notes = storedSection?.notes || ''
    section.photos = (check.photos || [])
      .filter(photo => photo.section === section.key)
      .map(photo => ({
        id: `server-${photo.id}`,
        preview: photo.annotated_url || photo.url,
        image_base64: '',
        annotated_base64: '',
        notes: photo.notes || '',
        fromServer: true
      }))
  }

  if (check.checklist?.length) {
    checklistRows.value = check.checklist.map(item => ({
      physical_check_item_id: item.physical_check_item_id,
      item_label: item.item_label,
      is_present: item.is_present,
      notes: item.notes || ''
    }))
  }

  const inspector = check.signatures?.find(signature => signature.signer_type === 'inspector')
  const customer = check.signatures?.find(signature => signature.signer_type === 'customer_driver')
  inspectorName.value = inspector?.signer_name || ''
  customerName.value = customer?.signer_name || booking.value?.customer?.nama || ''
  inspectorSignature.value = inspector?.url || ''
  customerSignature.value = customer?.url || ''
}

const compressImage = (file) => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onerror = reject
    reader.onload = () => {
      const img = new Image()
      img.onerror = reject
      img.onload = () => {
        const maxSide = 1600
        const scale = Math.min(1, maxSide / Math.max(img.width, img.height))
        const canvas = document.createElement('canvas')
        canvas.width = Math.max(1, Math.round(img.width * scale))
        canvas.height = Math.max(1, Math.round(img.height * scale))
        const ctx = canvas.getContext('2d')
        ctx.fillStyle = '#ffffff'
        ctx.fillRect(0, 0, canvas.width, canvas.height)
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height)
        resolve(canvas.toDataURL('image/jpeg', 0.78))
      }
      img.src = reader.result
    }
    reader.readAsDataURL(file)
  })
}

const onPhotoSelect = async (event, section) => {
  if (readonly.value) return
  const files = Array.from(event.target.files || [])
  if (!files.length) return

  for (const file of files) {
    if (!file.type.startsWith('image/')) continue
    const dataUrl = await compressImage(file)
    section.photos.push({
      id: `${Date.now()}-${Math.random()}`,
      preview: dataUrl,
      image_base64: dataUrl,
      annotated_base64: '',
      notes: '',
      fromServer: false
    })
  }

  event.target.value = ''
}

const removePhoto = (section, photo) => {
  if (readonly.value) return
  section.photos = section.photos.filter(item => item.id !== photo.id)
}

const annotatorVisible = ref(false)
const annotatorCanvas = ref(null)
const annotatorPhoto = ref(null)
const annotatorSource = ref('')
const isAnnotating = ref(false)
let annotatorContext = null

const openAnnotator = async (photo) => {
  if (readonly.value || photo.fromServer) return
  annotatorPhoto.value = photo
  annotatorSource.value = photo.annotated_base64 || photo.image_base64
  annotatorVisible.value = true
  await nextTick()
  drawAnnotatorImage()
}

const drawAnnotatorImage = () => {
  const canvas = annotatorCanvas.value
  if (!canvas || !annotatorSource.value) return
  const wrapper = canvas.parentElement
  const ratio = window.devicePixelRatio || 1
  const width = wrapper.clientWidth || 720
  const height = Math.min(520, Math.max(260, width * 0.64))
  canvas.style.height = `${height}px`
  canvas.width = width * ratio
  canvas.height = height * ratio
  annotatorContext = canvas.getContext('2d')
  annotatorContext.scale(ratio, ratio)
  annotatorContext.lineWidth = 4
  annotatorContext.lineCap = 'round'
  annotatorContext.lineJoin = 'round'
  annotatorContext.strokeStyle = '#ef4444'

  const img = new Image()
  img.onload = () => {
    annotatorContext.clearRect(0, 0, width, height)
    const imageRatio = img.width / img.height
    const boxRatio = width / height
    let drawWidth = width
    let drawHeight = height
    let x = 0
    let y = 0

    if (imageRatio > boxRatio) {
      drawHeight = width / imageRatio
      y = (height - drawHeight) / 2
    } else {
      drawWidth = height * imageRatio
      x = (width - drawWidth) / 2
    }

    annotatorContext.fillStyle = '#ffffff'
    annotatorContext.fillRect(0, 0, width, height)
    annotatorContext.drawImage(img, x, y, drawWidth, drawHeight)
  }
  img.src = annotatorSource.value
}

const canvasPoint = (event, canvas) => {
  const rect = canvas.getBoundingClientRect()
  const point = event.touches?.[0] || event
  return {
    x: point.clientX - rect.left,
    y: point.clientY - rect.top
  }
}

const startAnnotating = (event) => {
  event.preventDefault()
  if (!annotatorContext) return
  const pos = canvasPoint(event, annotatorCanvas.value)
  isAnnotating.value = true
  annotatorContext.beginPath()
  annotatorContext.moveTo(pos.x, pos.y)
}

const moveAnnotating = (event) => {
  if (!isAnnotating.value || !annotatorContext) return
  event.preventDefault()
  const pos = canvasPoint(event, annotatorCanvas.value)
  annotatorContext.lineTo(pos.x, pos.y)
  annotatorContext.stroke()
}

const endAnnotating = () => {
  isAnnotating.value = false
}

const resetAnnotation = () => {
  if (!annotatorPhoto.value) return
  annotatorSource.value = annotatorPhoto.value.image_base64
  drawAnnotatorImage()
}

const saveAnnotation = () => {
  if (!annotatorPhoto.value || !annotatorCanvas.value) return
  const dataUrl = annotatorCanvas.value.toDataURL('image/jpeg', 0.82)
  annotatorPhoto.value.annotated_base64 = dataUrl
  annotatorPhoto.value.preview = dataUrl
  annotatorVisible.value = false
}

const setFuelMarker = (event) => {
  if (readonly.value) return
  const target = event.currentTarget
  const rect = target.getBoundingClientRect()
  const point = event.touches?.[0] || event
  fuelMarker.value = {
    x: Math.max(0, Math.min(100, ((point.clientX - rect.left) / rect.width) * 100)),
    y: Math.max(0, Math.min(100, ((point.clientY - rect.top) / rect.height) * 100))
  }
}

const fuelOptions = [
  'E',
  '1/8',
  '1/4',
  '3/8',
  '1/2',
  '5/8',
  '3/4',
  '7/8',
  'F'
]

const validateForm = () => {
  if (!kmOdometer.value && kmOdometer.value !== 0) {
    toast.add({ severity: 'warn', summary: 'KM belum diisi', detail: 'Isi kilometer terakhir secara manual.', life: 4000 })
    return false
  }

  const missingPhoto = photoRequiredSections.find(key => {
    const section = sections.value.find(item => item.key === key)
    return !section?.photos?.length
  })

  if (missingPhoto) {
    const section = sections.value.find(item => item.key === missingPhoto)
    toast.add({ severity: 'warn', summary: 'Foto belum lengkap', detail: `Tambahkan foto untuk ${section.label}.`, life: 4000 })
    return false
  }

  if (!fuelMarker.value) {
    toast.add({ severity: 'warn', summary: 'BBM belum ditandai', detail: 'Ketuk gambar indikator BBM untuk menandai posisi.', life: 4000 })
    return false
  }

  if (!inspectorSignature.value || !customerSignature.value) {
    toast.add({ severity: 'warn', summary: 'Tanda tangan belum lengkap', detail: 'Lengkapi tanda tangan tim cek fisik dan user/driver.', life: 4000 })
    return false
  }

  return true
}

const submit = async () => {
  if (!validateForm()) return

  const payload = {
    booking_id: Number(bookingId.value),
    type: type.value,
    km_odometer: kmOdometer.value,
    fuel_level: fuelLevel.value,
    fuel_marker_x: fuelMarker.value?.x,
    fuel_marker_y: fuelMarker.value?.y,
    notes: generalNotes.value,
    sections: sections.value.map(section => ({
      section: section.key,
      notes: section.notes
    })),
    photos: sections.value.flatMap(section =>
      section.photos
        .filter(photo => !photo.fromServer)
        .map(photo => ({
          section: section.key,
          image_base64: photo.image_base64,
          annotated_base64: photo.annotated_base64 || null,
          notes: photo.notes
        }))
    ),
    checklist: checklistRows.value.map(item => ({
      physical_check_item_id: item.physical_check_item_id,
      item_label: item.item_label,
      is_present: item.is_present,
      notes: item.notes
    })),
    signatures: [
      {
        signer_type: 'inspector',
        signer_name: inspectorName.value,
        signature_base64: inspectorSignature.value
      },
      {
        signer_type: 'customer_driver',
        signer_name: customerName.value,
        signature_base64: customerSignature.value
      }
    ]
  }

  await store(payload)
  router.push({ name: 'PhysicalCheckList' })
}

onMounted(loadData)
</script>

<template>
  <div class="app-page check-form-page">
    <div class="form-head">
      <Button icon="pi pi-arrow-left" text rounded aria-label="Kembali" @click="router.push({ name: 'PhysicalCheckList' })" />
      <div class="head-main">
        <p class="eyebrow">{{ title }}</p>
        <h1>{{ booking?.kode_booking || 'Memuat booking...' }}</h1>
      </div>
      <Tag
        v-if="existingCheck"
        :value="statusText(existingCheck.status)"
        :severity="statusSeverity(existingCheck.status)"
      />
    </div>

    <div v-if="!booking" class="app-card p-5">
      <Skeleton height="120px" />
    </div>

    <template v-else>
      <div class="summary-band">
        <div class="summary-item wide">
          <span>Kendaraan</span>
          <strong>{{ vehicleTitle }}</strong>
          <small>{{ activeDetail?.unit?.no_polisi || 'No polisi belum tersedia' }}</small>
        </div>
        <div class="summary-item">
          <span>Pelanggan</span>
          <strong>{{ booking.customer?.nama || '-' }}</strong>
          <small>{{ booking.customer?.status || '-' }}</small>
        </div>
        <div class="summary-item">
          <span>Status</span>
          <BookingStatusBadge :status="booking.status" />
        </div>
        <div class="summary-item">
          <span>Sewa</span>
          <strong>{{ formatDateTime(activeDetail?.tgl_sewa) }}</strong>
          <small>{{ formatDateTime(activeDetail?.tgl_kembali) }}</small>
        </div>
      </div>

      <Message v-if="!eligibility.allowed && !readonly" severity="warn" :closable="false">
        {{ eligibility.reason }}
      </Message>

      <Message v-if="readonly" severity="info" :closable="false">
        Data cek fisik ini sudah {{ statusText(existingCheck.status).toLowerCase() }} dan ditampilkan dalam mode lihat.
      </Message>

      <section class="app-card form-section">
        <div class="section-title">
          <i class="pi pi-camera"></i>
          <div>
            <h2>Foto Kondisi Kendaraan</h2>
            <p>Foto bisa lebih dari satu per bagian dan bisa diberi coretan/notasi.</p>
          </div>
        </div>

        <div class="section-grid">
          <article v-for="section in sections" :key="section.key" class="photo-panel">
            <div class="panel-head">
              <strong>{{ section.label }}</strong>
              <label v-if="!readonly" class="photo-trigger">
                <i class="pi pi-camera"></i>
                <span>Tambah</span>
                <input
                  type="file"
                  accept="image/*"
                  capture="environment"
                  multiple
                  @change="onPhotoSelect($event, section)"
                />
              </label>
            </div>

            <Textarea
              v-model="section.notes"
              rows="2"
              autoResize
              :readonly="readonly"
              placeholder="Keterangan bagian ini..."
              class="w-full"
            />

            <div v-if="section.photos.length" class="photo-grid">
              <div v-for="photo in section.photos" :key="photo.id" class="photo-card">
                <img :src="photo.preview" :alt="section.label" />
                <Textarea
                  v-model="photo.notes"
                  rows="1"
                  autoResize
                  :readonly="readonly"
                  placeholder="Catatan foto..."
                  class="w-full"
                />
                <div v-if="!readonly" class="photo-actions">
                  <Button icon="pi pi-pencil" text rounded aria-label="Coret foto" @click="openAnnotator(photo)" />
                  <Button icon="pi pi-trash" text rounded severity="danger" aria-label="Hapus foto" @click="removePhoto(section, photo)" />
                </div>
              </div>
            </div>
            <div v-else class="empty-photo">
              <i class="pi pi-image"></i>
              <span>Belum ada foto</span>
            </div>
          </article>
        </div>
      </section>

      <section class="meter-grid">
        <div class="app-card form-section">
          <div class="section-title">
            <i class="pi pi-gauge"></i>
            <div>
              <h2>Kilometer</h2>
              <p>Gunakan foto bagian KM dan isi angka manual.</p>
            </div>
          </div>
          <InputNumber
            v-model="kmOdometer"
            :disabled="readonly"
            :min="0"
            inputId="km_odometer"
            class="w-full"
            suffix=" km"
            placeholder="KM terakhir"
          />
        </div>

        <div class="app-card form-section">
          <div class="section-title">
            <i class="pi pi-compass"></i>
            <div>
              <h2>Indikator BBM</h2>
              <p>Ketuk posisi jarum/indikator pada gambar.</p>
            </div>
          </div>
          <div class="fuel-wrap" @click="setFuelMarker" @touchstart.prevent="setFuelMarker">
            <img :src="fuelGaugeImage" alt="Indikator BBM" />
            <span
              v-if="fuelMarker"
              class="fuel-marker"
              :style="{ left: `${fuelMarker.x}%`, top: `${fuelMarker.y}%` }"
            ></span>
          </div>
          <Dropdown
            v-model="fuelLevel"
            :options="fuelOptions"
            :disabled="readonly"
            editable
            placeholder="Label BBM"
            class="w-full"
          />
        </div>
      </section>

      <section class="app-card form-section">
        <div class="section-title">
          <i class="pi pi-list-check"></i>
          <div>
            <h2>Perlengkapan</h2>
            <p>Daftar item berasal dari master perlengkapan cek fisik.</p>
          </div>
        </div>

        <DataTable :value="checklistRows" dataKey="item_label" responsiveLayout="scroll" class="checklist-table">
          <Column header="Ada" style="width: 80px">
            <template #body="{ data }">
              <Checkbox v-model="data.is_present" :binary="true" :disabled="readonly" />
            </template>
          </Column>
          <Column field="item_label" header="Item" style="min-width: 180px">
            <template #body="{ data }">
              <strong>{{ data.item_label }}</strong>
            </template>
          </Column>
          <Column header="Keterangan" style="min-width: 240px">
            <template #body="{ data }">
              <InputText v-model="data.notes" :readonly="readonly" placeholder="Keterangan..." class="w-full" />
            </template>
          </Column>
        </DataTable>
      </section>

      <section class="app-card form-section">
        <div class="section-title">
          <i class="pi pi-pencil"></i>
          <div>
            <h2>Tanda Tangan</h2>
            <p>Tim cek fisik dan user/driver yang menerima atau mengembalikan mobil.</p>
          </div>
        </div>
        <div class="signature-grid">
          <div>
            <InputText v-model="inspectorName" :readonly="readonly" placeholder="Nama tim cek fisik" class="w-full mb-2" />
            <SignaturePad v-model="inspectorSignature" label="Tim Cek Fisik" :disabled="readonly" />
          </div>
          <div>
            <InputText v-model="customerName" :readonly="readonly" placeholder="Nama user/driver" class="w-full mb-2" />
            <SignaturePad v-model="customerSignature" label="User / Driver" :disabled="readonly" />
          </div>
        </div>
      </section>

      <section class="app-card form-section">
        <div class="section-title">
          <i class="pi pi-align-left"></i>
          <div>
            <h2>Catatan Akhir</h2>
            <p>Tambahkan kondisi khusus atau temuan penting.</p>
          </div>
        </div>
        <Textarea
          v-model="generalNotes"
          rows="3"
          autoResize
          :readonly="readonly"
          placeholder="Catatan umum cek fisik..."
          class="w-full"
        />
      </section>

      <div class="sticky-actions">
        <Button label="Kembali" icon="pi pi-arrow-left" outlined @click="router.push({ name: 'PhysicalCheckList' })" />
        <Button
          v-if="!readonly"
          label="Simpan Cek Fisik"
          icon="pi pi-save"
          :disabled="!canSubmit"
          :loading="loading"
          @click="submit"
        />
      </div>
    </template>

    <Dialog v-model:visible="annotatorVisible" header="Coret / Notasi Foto" modal :style="{ width: 'min(920px, 96vw)' }" :breakpoints="{ '720px': '96vw' }">
      <div class="annotator">
        <canvas
          ref="annotatorCanvas"
          @mousedown="startAnnotating"
          @mousemove="moveAnnotating"
          @mouseup="endAnnotating"
          @mouseleave="endAnnotating"
          @touchstart="startAnnotating"
          @touchmove="moveAnnotating"
          @touchend="endAnnotating"
        ></canvas>
      </div>
      <template #footer>
        <Button label="Reset" icon="pi pi-refresh" text @click="resetAnnotation" />
        <Button label="Simpan Coretan" icon="pi pi-check" @click="saveAnnotation" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.check-form-page {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding-bottom: 78px;
}

.form-head {
  display: flex;
  align-items: center;
  gap: 10px;
}

.head-main {
  flex: 1;
  min-width: 0;
}

.head-main h1 {
  margin: 0;
  font-weight: 800;
  color: #0f172a;
}

.eyebrow {
  margin: 0 0 4px;
  color: #0891b2;
  font-weight: 800;
  letter-spacing: 0;
  text-transform: uppercase;
}

.summary-band {
  display: grid;
  grid-template-columns: 1.4fr 1fr auto 1.2fr;
  gap: 10px;
}

.summary-item {
  background: #ffffff;
  border: 1px solid #dbe4ee;
  border-radius: 8px;
  padding: 12px;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.summary-item span,
.summary-item small {
  color: #64748b;
}

.summary-item strong {
  color: #0f172a;
}

.form-section {
  padding: 14px;
}

.section-title {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 12px;
}

.section-title i {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  background: #e0f2fe;
  color: #0369a1;
  display: grid;
  place-items: center;
}

.section-title h2 {
  margin: 0;
  font-weight: 800;
  color: #0f172a;
}

.section-title p {
  margin: 3px 0 0;
  color: #64748b;
}

.section-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.photo-panel {
  border: 1px solid #e7edf4;
  border-radius: 8px;
  background: #f8fbfe;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.photo-trigger {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 34px;
  padding: 0 10px;
  border-radius: 8px;
  background: #0891b2;
  color: #ffffff;
  font-weight: 700;
  cursor: pointer;
}

.photo-trigger input {
  display: none;
}

.photo-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(132px, 1fr));
  gap: 10px;
}

.photo-card {
  border: 1px solid #dbe4ee;
  border-radius: 8px;
  overflow: hidden;
  background: #ffffff;
}

.photo-card img {
  width: 100%;
  aspect-ratio: 4 / 3;
  object-fit: cover;
  display: block;
}

.photo-card :deep(.p-textarea) {
  border-radius: 0;
  border-left: 0;
  border-right: 0;
}

.photo-actions {
  display: flex;
  justify-content: flex-end;
  gap: 4px;
  padding: 4px;
}

.empty-photo {
  min-height: 92px;
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
  display: grid;
  place-items: center;
  color: #94a3b8;
  gap: 4px;
}

.meter-grid {
  display: grid;
  grid-template-columns: minmax(260px, 0.8fr) minmax(320px, 1.2fr);
  gap: 12px;
}

.fuel-wrap {
  position: relative;
  width: 100%;
  max-width: 520px;
  margin: 0 auto 12px;
  border: 1px solid #dbe4ee;
  border-radius: 8px;
  overflow: hidden;
  background: #ffffff;
  cursor: crosshair;
  touch-action: none;
}

.fuel-wrap img {
  width: 100%;
  display: block;
}

.fuel-marker {
  position: absolute;
  width: 22px;
  height: 22px;
  border: 3px solid #ef4444;
  background: rgba(239, 68, 68, 0.18);
  border-radius: 999px;
  transform: translate(-50%, -50%);
  box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.86);
}

.signature-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.sticky-actions {
  position: sticky;
  bottom: 12px;
  z-index: 20;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 10px;
  background: rgba(255, 255, 255, 0.92);
  border: 1px solid #dbe4ee;
  border-radius: 8px;
  box-shadow: 0 18px 45px -28px rgba(15, 23, 42, 0.65);
  backdrop-filter: blur(12px);
}

.annotator {
  width: 100%;
  border: 1px solid #dbe4ee;
  border-radius: 8px;
  background: #0f172a;
  overflow: hidden;
}

.annotator canvas {
  width: 100%;
  display: block;
  touch-action: none;
}

@media (max-width: 1100px) {
  .summary-band {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 820px) {
  .section-grid,
  .meter-grid,
  .signature-grid {
    grid-template-columns: 1fr;
  }

  .summary-band {
    grid-template-columns: 1fr;
  }

  .sticky-actions {
    bottom: 8px;
  }
}

@media (max-width: 540px) {
  .form-head {
    align-items: flex-start;
  }

  .photo-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .sticky-actions {
    flex-direction: column;
  }

  .sticky-actions :deep(.p-button) {
    width: 100%;
  }
}
</style>
