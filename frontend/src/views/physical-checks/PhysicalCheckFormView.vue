<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { format } from 'date-fns'
import { useToast } from 'primevue/usetoast'
import { useBooking } from '../../composables/useBooking'
import { usePhysicalCheck } from '../../composables/usePhysicalCheck'
import SignaturePad from '../../components/physical-checks/SignaturePad.vue'
import BookingStatusBadge from '../../components/bookings/BookingStatusBadge.vue'
import fuelGaugeImage from '../../assets/fuel-gauge.svg'
import carBackImage from '../../assets/car/car back.svg'
import carFrontImage from '../../assets/car/car front.svg'
import carLeftImage from '../../assets/car/car left.svg'
import carRightImage from '../../assets/car/car right.svg'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Skeleton from 'primevue/skeleton'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import Toast from 'primevue/toast'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { fetchOne } = useBooking()
const {
  loading,
  items,
  fetchItems,
  fetchByBooking,
  requestCheck,
  store,
  fetchPublic,
  requestPublicOtp,
  storePublic,
  logPublicActivity
} = usePhysicalCheck()

const booking = ref(null)
const existingCheck = ref(null)
const kmOdometer = ref(null)
const fuelLevel = ref('')
const fuelMarker = ref(null)
const generalNotes = ref('')
const inspectorName = ref('')
const customerName = ref('')
const customerEmail = ref('')
const otpCode = ref('')
const otpSent = ref(false)
const inspectorSignature = ref('')
const customerSignature = ref('')
const checklistRows = ref([])
const activeStep = ref(0)
const galleryVisible = ref(false)
const galleryPhotos = ref([])
const galleryIndex = ref(0)
const galleryTitle = ref('')

const sections = ref([
  { key: 'front', label: 'Tampak depan', notes: '', photos: [], iconSrc: carFrontImage },
  { key: 'left', label: 'Samping kiri', notes: '', photos: [], iconSrc: carLeftImage },
  { key: 'right', label: 'Samping kanan', notes: '', photos: [], iconSrc: carRightImage },
  { key: 'rear', label: 'Tampak belakang', notes: '', photos: [], iconSrc: carBackImage },
  { key: 'interior', label: 'Interior', notes: '', photos: [], iconClass: 'pi pi-car' },
  { key: 'km', label: 'Foto KM terakhir', notes: '', photos: [], iconClass: 'pi pi-gauge' },
  { key: 'handover_selfie', label: 'Foto bersama penyewa', notes: '', photos: [], iconClass: 'pi pi-users', optional: true },
])

const photoRequiredSections = ['front', 'left', 'right', 'rear', 'interior', 'km']
const publicToken = computed(() => route.params.token)
const isPublicMode = computed(() => route.name === 'PublicPhysicalCheckForm')
const type = computed(() => isPublicMode.value ? existingCheck.value?.type : route.params.type)
const bookingId = computed(() => isPublicMode.value ? booking.value?.id : route.params.bookingId)
const isReturn = computed(() => type.value === 'return')
const title = computed(() => isReturn.value ? 'Cek Fisik Pengembalian' : 'Cek Fisik Keberangkatan')
const readonly = computed(() => ['completed', 'skipped'].includes(existingCheck.value?.status))
const canSubmit = computed(() => !readonly.value && eligibility.value.allowed)
const technicalReadonly = computed(() => readonly.value || isPublicMode.value)
const selfieSection = computed(() => sections.value.find(section => section.key === 'handover_selfie'))
const inspectionSections = computed(() => sections.value.filter(section => section.key !== 'handover_selfie'))

const steps = computed(() => [
  { key: 'photos', label: 'Foto', icon: 'pi pi-camera', complete: photoRequiredSections.every(hasSectionPhoto) },
  { key: 'meter', label: 'KM & BBM', icon: 'pi pi-gauge', complete: (kmOdometer.value || kmOdometer.value === 0) && !!fuelMarker.value },
  { key: 'checklist', label: 'Perlengkapan', icon: 'pi pi-list-check', complete: checklistRows.value.length > 0 },
  { key: 'signatures', label: 'TTD', icon: 'pi pi-pencil', complete: isPublicMode.value ? !!customerSignature.value : !!inspectorSignature.value },
  { key: 'review', label: 'Kirim', icon: 'pi pi-send', complete: isPublicMode.value ? !!otpCode.value : true },
])

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

const maskedEmail = computed(() => {
  const email = booking.value?.customer?.email || customerEmail.value
  if (!email || !email.includes('@')) return email || '-'
  const [name, domain] = email.split('@')
  return `${name.slice(0, 2)}***@${domain}`
})

const publicLink = computed(() => {
  if (!existingCheck.value?.public_token) return ''
  return `${window.location.origin}/physical-checks/public/${existingCheck.value.public_token}`
})

const activeGalleryPhoto = computed(() => galleryPhotos.value[galleryIndex.value] || null)

const resolveMediaUrl = (url) => {
  if (!url) return ''

  const apiRoot = import.meta.env.VITE_API_URL || import.meta.env.VITE_API_BASE_URL?.replace(/\/api\/?$/, '') || ''
  const normalizedApiRoot = apiRoot.replace(/\/$/, '')
  if (/^(data:|blob:)/i.test(url)) return url

  if (/^https?:\/\//i.test(url)) {
    try {
      const parsed = new URL(url)
      if (parsed.pathname.startsWith('/storage/')) {
        return `${normalizedApiRoot}${parsed.pathname}${parsed.search}`
      }
    } catch (err) {
      return url
    }

    return url
  }

  if (url.startsWith('/storage/')) {
    return `${normalizedApiRoot}${url}`
  }

  if (url.startsWith('storage/')) {
    return `${normalizedApiRoot}/${url}`
  }

  return url
}

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
  if (readonly.value) return { allowed: true, reason: null }
  if (!booking.value || !activeDetail.value) return { allowed: false, reason: 'Detail kendaraan belum tersedia.' }

  if (!isReturn.value && booking.value.status !== 'waiting_list') {
    return { allowed: false, reason: 'Cek keberangkatan hanya untuk booking Waiting List.' }
  }

  if (isReturn.value && booking.value.status !== 'rental_unit') {
    return { allowed: false, reason: 'Cek pengembalian hanya untuk booking Rental Unit.' }
  }

  const targetDate = isReturn.value ? activeDetail.value.tgl_kembali : activeDetail.value.tgl_sewa
  if (!targetDate) return { allowed: false, reason: 'Tanggal sewa/kembali belum tersedia.' }

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

const fuelOptions = ['E', '1/8', '1/4', '3/8', '1/2', '5/8', '3/4', '7/8', 'F']

function hasSectionPhoto(key) {
  return !!sections.value.find(section => section.key === key)?.photos?.length
}

const loadData = async () => {
  if (isPublicMode.value) {
    const payload = await fetchPublic(publicToken.value)
    booking.value = payload.booking
    existingCheck.value = payload.check
    items.value = payload.items || []
  } else {
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
  }

  initializeChecklist()
  hydrateExistingCheck()
  logStepOpened()
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
  customerEmail.value = booking.value?.customer?.email || ''

  for (const section of sections.value) {
    const storedSection = check.sections?.find(item => item.section === section.key)
    section.notes = storedSection?.notes || ''
    section.photos = (check.photos || [])
      .filter(photo => photo.section === section.key)
      .map(photo => ({
        id: `server-${photo.id}`,
        preview: resolveMediaUrl(photo.annotated_url || photo.url),
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
  inspectorSignature.value = resolveMediaUrl(inspector?.url) || ''
  customerSignature.value = resolveMediaUrl(customer?.url) || ''
}

const compressImage = (file) => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onerror = reject
    reader.onload = () => {
      const img = new Image()
      img.onerror = reject
      img.onload = () => {
        const maxSide = 1280
        const scale = Math.min(1, maxSide / Math.max(img.width, img.height))
        const canvas = document.createElement('canvas')
        canvas.width = Math.max(1, Math.round(img.width * scale))
        canvas.height = Math.max(1, Math.round(img.height * scale))
        const ctx = canvas.getContext('2d')
        ctx.fillStyle = '#ffffff'
        ctx.fillRect(0, 0, canvas.width, canvas.height)
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height)
        resolve(canvas.toDataURL('image/jpeg', 0.68))
      }
      img.src = reader.result
    }
    reader.readAsDataURL(file)
  })
}

const onPhotoSelect = async (event, section) => {
  if (technicalReadonly.value) return
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
  logFillActivity('photo_added', { section: section.key, count: files.length })
}

const removePhoto = (section, photo) => {
  if (technicalReadonly.value) return
  section.photos = section.photos.filter(item => item.id !== photo.id)
  logFillActivity('photo_removed', { section: section.key })
}

const openGallery = (section, photo) => {
  galleryPhotos.value = section.photos
  galleryIndex.value = Math.max(0, section.photos.findIndex(item => item.id === photo.id))
  galleryTitle.value = section.label
  galleryVisible.value = true
}

const showPreviousGalleryPhoto = () => {
  if (!galleryPhotos.value.length) return
  galleryIndex.value = (galleryIndex.value - 1 + galleryPhotos.value.length) % galleryPhotos.value.length
}

const showNextGalleryPhoto = () => {
  if (!galleryPhotos.value.length) return
  galleryIndex.value = (galleryIndex.value + 1) % galleryPhotos.value.length
}

const annotatorVisible = ref(false)
const annotatorCanvas = ref(null)
const annotatorPhoto = ref(null)
const annotatorSource = ref('')
const isAnnotating = ref(false)
let annotatorContext = null

const openAnnotator = async (photo) => {
  if (technicalReadonly.value || photo.fromServer) return
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
  const dataUrl = annotatorCanvas.value.toDataURL('image/jpeg', 0.76)
  annotatorPhoto.value.annotated_base64 = dataUrl
  annotatorPhoto.value.preview = dataUrl
  annotatorVisible.value = false
  logFillActivity('photo_annotated')
}

const setFuelMarker = (event) => {
  if (technicalReadonly.value) return
  const target = event.currentTarget
  const rect = target.getBoundingClientRect()
  const point = event.touches?.[0] || event
  fuelMarker.value = {
    x: Math.max(0, Math.min(100, ((point.clientX - rect.left) / rect.width) * 100)),
    y: Math.max(0, Math.min(100, ((point.clientY - rect.top) / rect.height) * 100))
  }
  logFillActivity('fuel_marked')
}

const validateStep = (index = activeStep.value) => {
  if (index === 0) {
    const missingPhoto = photoRequiredSections.find(key => !hasSectionPhoto(key))
    if (missingPhoto) {
      const section = sections.value.find(item => item.key === missingPhoto)
      toast.add({ severity: 'warn', summary: 'Foto belum lengkap', detail: `Tambahkan foto untuk ${section.label}.`, life: 4000 })
      return false
    }
  }

  if (index === 1) {
    if (!kmOdometer.value && kmOdometer.value !== 0) {
      toast.add({ severity: 'warn', summary: 'KM belum diisi', detail: 'Isi kilometer terakhir secara manual.', life: 4000 })
      return false
    }

    if (!fuelMarker.value) {
      toast.add({ severity: 'warn', summary: 'BBM belum ditandai', detail: 'Ketuk gambar indikator BBM untuk menandai posisi.', life: 4000 })
      return false
    }
  }

  if (index === 3 && !inspectorSignature.value && !isPublicMode.value) {
    toast.add({ severity: 'warn', summary: 'Tanda tangan belum lengkap', detail: 'Lengkapi tanda tangan tim cek fisik.', life: 4000 })
    return false
  }

  if (index === 3 && !customerSignature.value && isPublicMode.value) {
    toast.add({ severity: 'warn', summary: 'Tanda tangan belum lengkap', detail: 'Lengkapi tanda tangan penyewa.', life: 4000 })
    return false
  }

  if (index === 4 && isPublicMode.value && !otpCode.value) {
    toast.add({ severity: 'warn', summary: 'OTP belum diisi', detail: 'Masukkan kode OTP dari email penyewa.', life: 4000 })
    return false
  }

  return true
}

const validateForm = () => steps.value.every((_, index) => validateStep(index))

const goToStep = (index) => {
  if (readonly.value || index <= activeStep.value || validateStep(activeStep.value)) {
    activeStep.value = index
  }
}

const nextStep = () => {
  if (!validateStep()) return
  activeStep.value = Math.min(activeStep.value + 1, steps.value.length - 1)
}

const previousStep = () => {
  activeStep.value = Math.max(activeStep.value - 1, 0)
}

const buildPayload = () => ({
  booking_id: Number(bookingId.value),
  type: type.value,
  km_odometer: kmOdometer.value,
  fuel_level: fuelLevel.value,
  fuel_marker_x: fuelMarker.value?.x,
  fuel_marker_y: fuelMarker.value?.y,
  notes: generalNotes.value,
  customer_email: customerEmail.value,
  otp_code: otpCode.value,
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
    }
  ]
})

const buildPublicSignaturePayload = () => ({
  customer_email: customerEmail.value,
  otp_code: otpCode.value,
  signer_name: customerName.value,
  signature_base64: customerSignature.value
})

const sendOtp = async () => {
  if (!isPublicMode.value) return
  await requestPublicOtp(publicToken.value)
  otpSent.value = true
  logFillActivity('otp_requested_from_form')
}

const copyPublicLink = async () => {
  if (!publicLink.value) return
  if (navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(publicLink.value)
  } else {
    const input = document.createElement('input')
    input.value = publicLink.value
    document.body.appendChild(input)
    input.select()
    document.execCommand('copy')
    document.body.removeChild(input)
  }
  toast.add({ severity: 'success', summary: 'Link disalin', detail: 'Link anonim penyewa sudah disalin.', life: 3000 })
}

const submit = async () => {
  if (!validateForm()) return

  if (isPublicMode.value) {
    await storePublic(publicToken.value, buildPublicSignaturePayload())
    existingCheck.value = { ...existingCheck.value, status: 'completed' }
    logFillActivity('submit_completed')
    activeStep.value = steps.value.length - 1
    return
  }

  await store(buildPayload())
  router.push({ name: 'PhysicalCheckList' })
}

const logFillActivity = (event, context = {}) => {
  if (!isPublicMode.value || !publicToken.value || readonly.value) return
  logPublicActivity(publicToken.value, event, context)
}

const logStepOpened = () => {
  logFillActivity('step_opened', { step: steps.value[activeStep.value]?.key })
}

watch(activeStep, logStepOpened)
watch([inspectorSignature, customerSignature], () => logFillActivity('signature_updated'))

onMounted(loadData)
</script>

<template>
  <div class="app-page check-form-page" :class="{ 'public-page': isPublicMode }">
    <Toast />
    <div class="detail-page-header">
      <div class="header-main">
        <Button
          v-if="!isPublicMode"
          icon="pi pi-arrow-left"
          text
          rounded
          class="back-button"
          aria-label="Kembali"
          @click="router.push({ name: 'PhysicalCheckList' })"
        />
        <div class="title-block">
          <p class="eyebrow">{{ title }}</p>
          <h1>{{ booking?.kode_booking || 'Memuat booking...' }}</h1>
          <span>{{ isPublicMode ? 'Form penyewa' : 'Form internal CS' }}</span>
        </div>
      </div>
      <div class="header-actions">
        <Button
          v-if="!isPublicMode && publicLink"
          label="Salin Link Penyewa"
          icon="pi pi-link"
          class="btn-pill btn-primary"
          @click="copyPublicLink"
        />
        <Tag
          v-if="existingCheck"
          :value="statusText(existingCheck.status)"
          :severity="statusSeverity(existingCheck.status)"
        />
      </div>
    </div>

    <div v-if="!booking" class="app-card loading-card">
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
          <span>Penyewa</span>
          <strong>{{ booking.customer?.nama || '-' }}</strong>
          <small>{{ maskedEmail }}</small>
        </div>
        <div class="summary-item">
          <span>Status booking</span>
          <BookingStatusBadge :status="booking.status" />
        </div>
        <div class="summary-item">
          <span>Periode</span>
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

      <div class="wizard-shell">
        <aside class="app-card step-card">
          <button
            v-for="(step, index) in steps"
            :key="step.key"
            class="step-button"
            :class="{ active: activeStep === index, complete: step.complete }"
            type="button"
            @click="goToStep(index)"
          >
            <i :class="step.icon"></i>
            <span>{{ step.label }}</span>
            <small>{{ index + 1 }}</small>
          </button>
        </aside>

        <main class="wizard-content">
          <section v-if="activeStep === 0" class="app-card form-section">
            <div class="app-section-header">
              <div class="section-heading">
                <i class="pi pi-camera"></i>
                <div>
                  <h2>Foto Kondisi Kendaraan</h2>
                  <p>Ambil foto tiap sisi kendaraan dan KM sebagai bukti kondisi unit.</p>
                </div>
              </div>
            </div>

            <div class="section-body">
              <div class="section-grid">
                <article v-for="section in inspectionSections" :key="section.key" class="photo-panel">
                  <div class="panel-head">
                    <div class="panel-title">
                      <span class="photo-visual" :class="{ 'photo-visual-icon': !section.iconSrc }">
                        <img v-if="section.iconSrc" :src="section.iconSrc" :alt="section.label" />
                        <i v-else :class="section.iconClass"></i>
                      </span>
                      <strong>{{ section.label }}</strong>
                    </div>
                    <label v-if="!technicalReadonly" class="photo-trigger btn-pill btn-primary">
                      <i class="pi pi-camera"></i>
                      <span>Ambil</span>
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
                    :disabled="technicalReadonly"
                    placeholder="Keterangan bagian ini..."
                    class="full-control"
                  />

                  <div v-if="section.photos.length" class="photo-grid">
                    <div v-for="photo in section.photos" :key="photo.id" class="photo-card">
                      <button type="button" class="photo-preview-button" @click="openGallery(section, photo)">
                        <img :src="photo.preview" :alt="section.label" />
                      </button>
                      <Textarea
                        v-model="photo.notes"
                        rows="1"
                        autoResize
                        :readonly="technicalReadonly"
                        :disabled="technicalReadonly"
                        placeholder="Catatan foto..."
                        class="full-control"
                      />
                      <div v-if="!technicalReadonly" class="photo-actions">
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
            </div>
          </section>

          <section v-else-if="activeStep === 1" class="meter-grid">
            <div class="app-card form-section">
              <div class="app-section-header">
                <div class="section-heading">
                  <i class="pi pi-gauge"></i>
                  <div>
                    <h2>Kilometer</h2>
                    <p>Isi angka KM terakhir sesuai foto odometer.</p>
                  </div>
                </div>
              </div>
              <div class="section-body">
                <InputNumber
                  v-model="kmOdometer"
                  :disabled="technicalReadonly"
                  :min="0"
                  inputId="km_odometer"
                  class="full-control"
                  suffix=" km"
                  placeholder="KM terakhir"
                  @blur="logFillActivity('km_filled')"
                />
              </div>
            </div>

            <div class="app-card form-section">
              <div class="app-section-header">
                <div class="section-heading">
                  <i class="pi pi-compass"></i>
                  <div>
                    <h2>Indikator BBM</h2>
                    <p>Ketuk posisi jarum atau indikator pada gambar.</p>
                  </div>
                </div>
              </div>
              <div class="section-body">
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
                  :disabled="technicalReadonly"
                  editable
                  placeholder="Label BBM"
                  class="full-control"
                  @change="logFillActivity('fuel_label_changed')"
                />
              </div>
            </div>
          </section>

          <section v-else-if="activeStep === 2" class="app-card form-section">
            <div class="app-section-header">
              <div class="section-heading">
                <i class="pi pi-list-check"></i>
                <div>
                  <h2>Perlengkapan</h2>
                  <p>Centang item yang tersedia dan beri catatan bila perlu.</p>
                </div>
              </div>
            </div>

            <div class="section-body checklist-list">
              <article v-for="row in checklistRows" :key="row.item_label" class="checklist-row">
                <Checkbox v-model="row.is_present" :binary="true" :disabled="technicalReadonly" @change="logFillActivity('checklist_changed', { item: row.item_label })" />
                <div>
                  <strong>{{ row.item_label }}</strong>
                  <InputText v-model="row.notes" :readonly="technicalReadonly" :disabled="technicalReadonly" placeholder="Keterangan..." class="full-control" />
                </div>
              </article>
            </div>
          </section>

          <section v-else-if="activeStep === 3" class="app-card form-section">
            <div class="app-section-header">
              <div class="section-heading">
                <i class="pi pi-pencil"></i>
                <div>
                  <h2>Tanda Tangan</h2>
                  <p>TTD tim cek fisik dan penyewa yang menerima atau mengembalikan unit.</p>
                </div>
              </div>
            </div>

            <div class="section-body signature-grid">
              <div class="signature-panel">
                <InputText v-model="inspectorName" :readonly="readonly || isPublicMode" :disabled="isPublicMode" placeholder="Nama tim cek fisik" class="full-control" />
                <SignaturePad v-model="inspectorSignature" label="Tim Cek Fisik" :disabled="readonly || isPublicMode" />
              </div>
              <div v-if="isPublicMode || customerSignature || readonly" class="signature-panel">
                <InputText v-model="customerName" :readonly="readonly" placeholder="Nama penyewa" class="full-control" />
                <SignaturePad v-model="customerSignature" label="Penyewa" :disabled="readonly" />
              </div>
              <div v-if="!isPublicMode && selfieSection" class="signature-panel handover-panel">
                <div class="panel-head">
                  <div class="panel-title">
                    <span class="photo-visual photo-visual-icon">
                      <i :class="selfieSection.iconClass"></i>
                    </span>
                    <div>
                      <strong>{{ selfieSection.label }}</strong>
                      <small>Opsional, dilakukan di akhir bila diperlukan.</small>
                    </div>
                  </div>
                  <label v-if="!readonly" class="photo-trigger btn-pill btn-primary">
                    <i class="pi pi-camera"></i>
                    <span>Ambil</span>
                    <input
                      type="file"
                      accept="image/*"
                      capture="user"
                      multiple
                      @change="onPhotoSelect($event, selfieSection)"
                    />
                  </label>
                </div>
                <Textarea
                  v-model="selfieSection.notes"
                  rows="2"
                  autoResize
                  :readonly="readonly"
                  placeholder="Keterangan foto bersama..."
                  class="full-control"
                />
                <div v-if="selfieSection.photos.length" class="photo-grid">
                  <div v-for="photo in selfieSection.photos" :key="photo.id" class="photo-card">
                    <button type="button" class="photo-preview-button" @click="openGallery(selfieSection, photo)">
                      <img :src="photo.preview" :alt="selfieSection.label" />
                    </button>
                    <Textarea
                      v-model="photo.notes"
                      rows="1"
                      autoResize
                      :readonly="readonly"
                      placeholder="Catatan foto..."
                      class="full-control"
                    />
                    <div v-if="!readonly" class="photo-actions">
                      <Button icon="pi pi-pencil" text rounded aria-label="Coret foto" @click="openAnnotator(photo)" />
                      <Button icon="pi pi-trash" text rounded severity="danger" aria-label="Hapus foto" @click="removePhoto(selfieSection, photo)" />
                    </div>
                  </div>
                </div>
                <div v-else class="empty-photo optional-photo">
                  <i class="pi pi-image"></i>
                  <span>Foto bersama belum ditambahkan</span>
                </div>
              </div>
            </div>
          </section>

          <section v-else class="app-card form-section">
            <div class="app-section-header">
              <div class="section-heading">
                <i class="pi pi-send"></i>
                <div>
                  <h2>Review & Kirim</h2>
                  <p>Periksa catatan akhir sebelum cek fisik dikirim.</p>
                </div>
              </div>
            </div>

            <div class="section-body review-grid">
              <div>
                <label class="field-label" for="general_notes">Catatan akhir</label>
                <Textarea
                  id="general_notes"
                  v-model="generalNotes"
                  rows="4"
                  autoResize
                  :readonly="readonly"
                  placeholder="Catatan umum cek fisik..."
                  class="full-control"
                  @blur="logFillActivity('notes_filled')"
                />
              </div>

              <div v-if="isPublicMode && !readonly" class="app-muted-panel otp-panel">
                <div>
                  <strong>Verifikasi OTP penyewa</strong>
                  <span>Kode dikirim ke {{ maskedEmail }} dan berlaku 10 menit.</span>
                </div>
                <Button
                  :label="otpSent ? 'Kirim Ulang OTP' : 'Kirim OTP'"
                  icon="pi pi-envelope"
                  class="btn-pill btn-primary"
                  :loading="loading"
                  @click="sendOtp"
                />
                <InputText
                  v-model="otpCode"
                  inputmode="numeric"
                  maxlength="6"
                  placeholder="6 digit OTP"
                  class="full-control otp-input"
                  @blur="logFillActivity('otp_entered')"
                />
              </div>

              <div class="review-summary">
                <div><span>Foto</span><strong>{{ sections.reduce((sum, section) => sum + section.photos.length, 0) }}</strong></div>
                <div><span>Checklist</span><strong>{{ checklistRows.length }}</strong></div>
                <div><span>KM</span><strong>{{ kmOdometer ?? '-' }}</strong></div>
                <div><span>BBM</span><strong>{{ fuelLevel || '-' }}</strong></div>
              </div>
            </div>
          </section>
        </main>
      </div>

      <div v-if="!readonly" class="sticky-actions">
        <Button
          label="Sebelumnya"
          icon="pi pi-arrow-left"
          class="btn-pill btn-secondary"
          :disabled="activeStep === 0 || loading"
          @click="previousStep"
        />
        <Button
          v-if="activeStep < steps.length - 1"
          label="Lanjut"
          icon="pi pi-arrow-right"
          iconPos="right"
          class="btn-pill btn-primary"
          :disabled="!canSubmit || loading"
          @click="nextStep"
        />
        <Button
          v-else
          label="Kirim Cek Fisik"
          icon="pi pi-send"
          class="btn-pill btn-primary"
          :disabled="!canSubmit"
          :loading="loading"
          @click="submit"
        />
      </div>
    </template>

    <Dialog
      v-model:visible="annotatorVisible"
      header="Coret / Notasi Foto"
      modal
      class="custom-dialog"
      :style="{ width: 'min(920px, 96vw)' }"
      :breakpoints="{ '720px': '96vw' }"
    >
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
        <Button label="Reset" icon="pi pi-refresh" class="app-dialog-button app-dialog-button-secondary" @click="resetAnnotation" />
        <Button label="Simpan Coretan" icon="pi pi-check" class="app-dialog-button app-dialog-button-primary" @click="saveAnnotation" />
      </template>
    </Dialog>

    <Dialog
      v-model:visible="galleryVisible"
      :header="galleryTitle"
      modal
      class="custom-dialog gallery-dialog"
      :style="{ width: 'min(980px, 96vw)' }"
      :breakpoints="{ '720px': '96vw' }"
    >
      <div v-if="activeGalleryPhoto" class="gallery-viewer">
        <div class="gallery-stage">
          <Button
            v-if="galleryPhotos.length > 1"
            icon="pi pi-chevron-left"
            text
            rounded
            class="gallery-nav gallery-nav-left"
            aria-label="Foto sebelumnya"
            @click="showPreviousGalleryPhoto"
          />
          <img :src="activeGalleryPhoto.preview" :alt="galleryTitle" />
          <Button
            v-if="galleryPhotos.length > 1"
            icon="pi pi-chevron-right"
            text
            rounded
            class="gallery-nav gallery-nav-right"
            aria-label="Foto berikutnya"
            @click="showNextGalleryPhoto"
          />
        </div>
        <p v-if="activeGalleryPhoto.notes" class="gallery-note">{{ activeGalleryPhoto.notes }}</p>
        <div v-if="galleryPhotos.length > 1" class="gallery-thumbs">
          <button
            v-for="(photo, index) in galleryPhotos"
            :key="photo.id"
            type="button"
            class="gallery-thumb"
            :class="{ active: index === galleryIndex }"
            @click="galleryIndex = index"
          >
            <img :src="photo.preview" :alt="`${galleryTitle} ${index + 1}`" />
          </button>
        </div>
      </div>
    </Dialog>
  </div>
</template>

<style scoped>
.check-form-page {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
  min-height: 100vh;
  padding: var(--space-2xl);
  padding-bottom: 92px;
  background: var(--page-bg);
}

.public-page {
  max-width: 1120px;
  margin: 0 auto;
}

.detail-page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-lg);
}

.header-main {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  min-width: 0;
}

.title-block {
  min-width: 0;
}

.title-block h1,
.title-block p,
.title-block span {
  margin: 0;
}

.title-block h1 {
  overflow-wrap: anywhere;
}

.title-block span,
.eyebrow {
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 600;
}

.eyebrow {
  color: var(--info-cyan);
  text-transform: uppercase;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
}

.app-card {
  overflow: hidden;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
}

.loading-card {
  padding: var(--space-xl);
}

.summary-band {
  display: grid;
  grid-template-columns: 1.4fr 1fr auto 1.2fr;
  gap: var(--space-md);
}

.summary-item {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: var(--space-xs);
  padding: var(--space-lg);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
}

.summary-item span,
.summary-item small {
  color: var(--text-secondary);
  font-size: 12px;
}

.summary-item strong {
  color: var(--text-primary);
  overflow-wrap: anywhere;
}

.wizard-shell {
  display: grid;
  grid-template-columns: 220px minmax(0, 1fr);
  gap: var(--space-lg);
  align-items: start;
}

.step-card {
  position: sticky;
  top: var(--space-lg);
  display: grid;
  gap: var(--space-sm);
  padding: var(--space-md);
}

.step-button {
  display: grid;
  grid-template-columns: 28px 1fr auto;
  align-items: center;
  gap: var(--space-sm);
  width: 100%;
  min-height: 42px;
  border: 1px solid transparent;
  border-radius: var(--radius-default);
  background: transparent;
  color: var(--text-secondary);
  text-align: left;
  cursor: pointer;
}

.step-button i {
  display: grid;
  width: 28px;
  height: 28px;
  place-items: center;
  border-radius: var(--radius-sm);
  background: var(--card-bg);
}

.step-button span {
  min-width: 0;
  font-size: 13px;
  font-weight: 700;
}

.step-button small {
  display: grid;
  width: 22px;
  height: 22px;
  place-items: center;
  border-radius: var(--radius-full);
  background: var(--card-bg);
  font-size: 11px;
}

.step-button.active,
.step-button.complete {
  border-color: var(--surface-border);
  background: var(--card-bg);
  color: var(--text-primary);
}

.step-button.active i,
.step-button.complete small {
  background: var(--text-primary);
  color: var(--text-white);
}

.wizard-content {
  min-width: 0;
}

.form-section {
  min-width: 0;
}

.app-section-header {
  display: flex;
  min-height: 54px;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-md);
  padding: var(--space-lg);
  border-bottom: 1px solid var(--surface-border);
}

.section-heading {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  min-width: 0;
}

.section-heading > i {
  display: grid;
  width: 34px;
  height: 34px;
  flex: 0 0 auto;
  place-items: center;
  border-radius: var(--radius-sm);
  background: var(--card-bg);
  color: var(--info-cyan);
}

.section-heading h2,
.section-heading p {
  margin: 0;
}

.section-heading p {
  color: var(--text-secondary);
  font-size: 12px;
}

.section-body {
  padding: var(--space-lg);
}

.section-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-md);
}

.photo-panel {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: var(--space-md);
  padding: var(--space-md);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--card-bg);
}

.panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-sm);
}

.panel-title {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: var(--space-sm);
}

.panel-title > div {
  min-width: 0;
}

.panel-title small {
  display: block;
  margin-top: 2px;
  color: var(--text-secondary);
  font-size: 11px;
  line-height: 1.35;
}

.panel-head strong {
  min-width: 0;
  overflow-wrap: anywhere;
}

.photo-visual {
  display: grid;
  width: 50px;
  height: 38px;
  flex: 0 0 auto;
  place-items: center;
  overflow: hidden;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-sm);
  background: var(--surface-default);
}

.photo-visual img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  padding: 3px;
}

.photo-visual-icon {
  width: 38px;
  color: var(--info-cyan);
  font-size: 17px;
}

.photo-trigger {
  min-height: 34px;
  padding: 8px 12px;
}

.photo-trigger input {
  display: none;
}

.full-control,
.full-control :deep(input) {
  width: 100%;
}

.photo-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(132px, 1fr));
  gap: var(--space-md);
}

.photo-card {
  overflow: hidden;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
}

.photo-preview-button,
.gallery-thumb {
  display: block;
  width: 100%;
  padding: 0;
  border: 0;
  background: transparent;
  cursor: zoom-in;
}

.photo-card img {
  display: block;
  width: 100%;
  aspect-ratio: 4 / 3;
  object-fit: cover;
}

.photo-card :deep(.p-textarea) {
  border-right: 0;
  border-left: 0;
  border-radius: 0;
}

.photo-actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-xs);
  padding: var(--space-xs);
}

.empty-photo {
  display: grid;
  min-height: 92px;
  place-items: center;
  gap: var(--space-xs);
  border: 1px dashed var(--neutral-4);
  border-radius: var(--radius-default);
  color: var(--text-tertiary);
}

.meter-grid {
  display: grid;
  grid-template-columns: minmax(240px, 0.8fr) minmax(300px, 1.2fr);
  gap: var(--space-lg);
}

.fuel-wrap {
  position: relative;
  width: 100%;
  max-width: 520px;
  margin: 0 auto var(--space-md);
  overflow: hidden;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  cursor: crosshair;
  touch-action: none;
}

.fuel-wrap img {
  display: block;
  width: 100%;
}

.fuel-marker {
  position: absolute;
  width: 22px;
  height: 22px;
  border: 3px solid var(--negative);
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--negative) 18%, transparent);
  box-shadow: 0 0 0 4px var(--surface-default);
  transform: translate(-50%, -50%);
}

.checklist-list {
  display: grid;
  gap: var(--space-md);
}

.checklist-row {
  display: grid;
  grid-template-columns: 30px minmax(0, 1fr);
  gap: var(--space-md);
  align-items: start;
  padding: var(--space-md);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--card-bg);
}

.checklist-row strong {
  display: block;
  margin-bottom: var(--space-sm);
}

.signature-grid,
.review-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-md);
}

.signature-panel {
  display: grid;
  gap: var(--space-md);
  min-width: 0;
}

.handover-panel {
  grid-column: 1 / -1;
}

.optional-photo {
  min-height: 76px;
}

.field-label {
  display: block;
  margin-bottom: var(--space-sm);
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 700;
}

.app-muted-panel {
  display: grid;
  gap: var(--space-md);
  padding: var(--space-lg);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--card-bg);
}

.otp-panel span {
  display: block;
  margin-top: var(--space-xs);
  color: var(--text-secondary);
  font-size: 12px;
}

.otp-input :deep(input),
.otp-input {
  font-family: var(--font-mono);
  font-size: 18px;
  letter-spacing: 0;
}

.review-summary {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-md);
}

.review-summary div {
  display: grid;
  gap: var(--space-xs);
  padding: var(--space-md);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--card-bg);
}

.review-summary span {
  color: var(--text-secondary);
  font-size: 12px;
}

.review-summary strong {
  font-family: var(--font-mono);
  overflow-wrap: anywhere;
}

.sticky-actions {
  position: sticky;
  bottom: var(--space-md);
  z-index: 20;
  display: flex;
  justify-content: flex-end;
  gap: var(--space-md);
  padding: var(--space-md);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: color-mix(in srgb, var(--surface-default) 92%, transparent);
  box-shadow: var(--shadow-card-big);
  backdrop-filter: blur(12px);
}

.annotator {
  width: 100%;
  overflow: hidden;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--text-primary);
}

.annotator canvas {
  display: block;
  width: 100%;
  touch-action: none;
}

.gallery-viewer {
  display: grid;
  gap: var(--space-md);
}

.gallery-stage {
  position: relative;
  display: grid;
  min-height: 280px;
  place-items: center;
  overflow: hidden;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--text-primary);
}

.gallery-stage img {
  display: block;
  max-width: 100%;
  max-height: 72vh;
  object-fit: contain;
}

.gallery-nav {
  position: absolute;
  top: 50%;
  z-index: 2;
  transform: translateY(-50%);
  background: color-mix(in srgb, var(--surface-default) 88%, transparent);
  box-shadow: var(--shadow-tile);
}

.gallery-nav-left {
  left: var(--space-md);
}

.gallery-nav-right {
  right: var(--space-md);
}

.gallery-note {
  margin: 0;
  color: var(--text-secondary);
  font-size: 13px;
}

.gallery-thumbs {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(72px, 1fr));
  gap: var(--space-sm);
}

.gallery-thumb {
  overflow: hidden;
  border: 2px solid transparent;
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.gallery-thumb.active {
  border-color: var(--info-cyan);
}

.gallery-thumb img {
  display: block;
  width: 100%;
  aspect-ratio: 4 / 3;
  object-fit: cover;
}

@media (max-width: 1100px) {
  .summary-band {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .wizard-shell {
    grid-template-columns: 1fr;
  }

  .step-card {
    position: static;
    grid-template-columns: repeat(5, minmax(88px, 1fr));
    overflow-x: auto;
  }

  .step-button {
    grid-template-columns: 1fr;
    justify-items: center;
    text-align: center;
  }

  .step-button small {
    display: none;
  }
}

@media (max-width: 820px) {
  .check-form-page {
    padding: var(--space-lg);
    padding-bottom: 112px;
  }

  .detail-page-header,
  .header-main {
    align-items: flex-start;
  }

  .detail-page-header,
  .summary-band,
  .section-grid,
  .meter-grid,
  .signature-grid,
  .review-grid {
    grid-template-columns: 1fr;
  }

  .detail-page-header {
    display: grid;
  }

  .step-card {
    display: flex;
  }

  .step-button {
    min-width: 92px;
  }
}

@media (max-width: 540px) {
  .photo-grid,
  .review-summary {
    grid-template-columns: 1fr;
  }

  .sticky-actions {
    right: var(--space-lg);
    left: var(--space-lg);
    display: grid;
    grid-template-columns: 1fr;
  }

  .sticky-actions :deep(.p-button) {
    width: 100%;
  }
}
</style>
