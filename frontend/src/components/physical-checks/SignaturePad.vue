<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import Button from 'primevue/button'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  label: {
    type: String,
    required: true
  },
  disabled: Boolean
})

const emit = defineEmits(['update:modelValue'])

const canvasRef = ref(null)
const isDrawing = ref(false)
let context = null

const resizeCanvas = () => {
  const canvas = canvasRef.value
  if (!canvas) return

  const ratio = window.devicePixelRatio || 1
  const rect = canvas.getBoundingClientRect()
  canvas.width = Math.max(1, Math.floor(rect.width * ratio))
  canvas.height = Math.max(1, Math.floor(rect.height * ratio))
  context = canvas.getContext('2d')
  context.scale(ratio, ratio)
  context.lineWidth = 2.5
  context.lineCap = 'round'
  context.lineJoin = 'round'
  context.strokeStyle = '#0f172a'

  if (props.modelValue) drawImage(props.modelValue)
}

const drawImage = (src) => {
  const canvas = canvasRef.value
  if (!canvas || !context || !src) return

  const img = new Image()
  img.onload = () => {
    const rect = canvas.getBoundingClientRect()
    context.clearRect(0, 0, rect.width, rect.height)
    context.drawImage(img, 0, 0, rect.width, rect.height)
  }
  img.src = src
}

const pointerPosition = (event) => {
  const canvas = canvasRef.value
  const rect = canvas.getBoundingClientRect()
  const point = event.touches?.[0] || event
  return {
    x: point.clientX - rect.left,
    y: point.clientY - rect.top
  }
}

const start = (event) => {
  if (props.disabled) return
  event.preventDefault()
  const pos = pointerPosition(event)
  isDrawing.value = true
  context.beginPath()
  context.moveTo(pos.x, pos.y)
}

const move = (event) => {
  if (!isDrawing.value || props.disabled) return
  event.preventDefault()
  const pos = pointerPosition(event)
  context.lineTo(pos.x, pos.y)
  context.stroke()
}

const end = () => {
  if (!isDrawing.value) return
  isDrawing.value = false
  emit('update:modelValue', canvasRef.value.toDataURL('image/png'))
}

const clear = () => {
  if (props.disabled) return
  const canvas = canvasRef.value
  const rect = canvas.getBoundingClientRect()
  context.clearRect(0, 0, rect.width, rect.height)
  emit('update:modelValue', '')
}

watch(() => props.modelValue, (value) => {
  if (value) drawImage(value)
})

onMounted(() => {
  resizeCanvas()
  window.addEventListener('resize', resizeCanvas)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', resizeCanvas)
})
</script>

<template>
  <div class="signature-box">
    <div class="signature-head">
      <span>{{ label }}</span>
      <Button
        icon="pi pi-eraser"
        text
        rounded
        size="small"
        aria-label="Bersihkan tanda tangan"
        v-tooltip.top="'Bersihkan'"
        :disabled="disabled"
        @click="clear"
      />
    </div>
    <canvas
      ref="canvasRef"
      class="signature-canvas"
      :class="{ disabled }"
      @mousedown="start"
      @mousemove="move"
      @mouseup="end"
      @mouseleave="end"
      @touchstart="start"
      @touchmove="move"
      @touchend="end"
    ></canvas>
  </div>
</template>

<style scoped>
.signature-box {
  border: 1px solid #dbe4ee;
  border-radius: 8px;
  background: #ffffff;
  overflow: hidden;
}

.signature-head {
  height: 42px;
  padding: 0 10px 0 12px;
  background: #f8fbfe;
  border-bottom: 1px solid #e7edf4;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-weight: 700;
  color: #334155;
}

.signature-canvas {
  width: 100%;
  height: 170px;
  display: block;
  background: linear-gradient(#ffffff, #ffffff), repeating-linear-gradient(0deg, transparent, transparent 30px, rgba(148, 163, 184, 0.12) 31px);
  touch-action: none;
}

.signature-canvas.disabled {
  background: #f8fafc;
  cursor: not-allowed;
}
</style>
