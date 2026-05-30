<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  width: { type: Number, default: 400 },
  height: { type: Number, default: 200 },
  penColor: { type: String, default: '#000000' },
  lineWidth: { type: Number, default: 2 },
  modelValue: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue'])

const canvasRef = ref(null)
let ctx = null
let drawing = false
let hasStrokes = false

onMounted(() => {
  const canvas = canvasRef.value
  ctx = canvas.getContext('2d')
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'

  if (props.modelValue) {
    loadImage(props.modelValue)
  }
})

onBeforeUnmount(() => {
  ctx = null
})

function loadImage(src) {
  const img = new Image()
  img.crossOrigin = 'anonymous'
  img.onload = () => {
    ctx.clearRect(0, 0, props.width, props.height)
    ctx.drawImage(img, 0, 0, props.width, props.height)
    hasStrokes = true
  }
  img.src = src
}

function getPos(e) {
  const rect = canvasRef.value.getBoundingClientRect()
  const touch = e.touches?.[0]
  const clientX = touch ? touch.clientX : e.clientX
  const clientY = touch ? touch.clientY : e.clientY
  return { x: clientX - rect.left, y: clientY - rect.top }
}

function startDraw(e) {
  e.preventDefault()
  drawing = true
  const { x, y } = getPos(e)
  ctx.beginPath()
  ctx.moveTo(x, y)
  ctx.strokeStyle = props.penColor
  ctx.lineWidth = props.lineWidth
}

function draw(e) {
  if (!drawing) return
  e.preventDefault()
  const { x, y } = getPos(e)
  ctx.lineTo(x, y)
  ctx.stroke()
  hasStrokes = true
}

function endDraw() {
  if (!drawing) return
  drawing = false
  ctx.closePath()
  emitData()
}

function emitData() {
  if (!hasStrokes) {
    emit('update:modelValue', null)
    return
  }
  const dataUrl = canvasRef.value.toDataURL('image/png')
  emit('update:modelValue', dataUrl)
}

function clear() {
  ctx.clearRect(0, 0, props.width, props.height)
  hasStrokes = false
  emit('update:modelValue', null)
}

defineExpose({ clear })
</script>

<template>
  <div class="signature-canvas-wrap">
    <canvas
      ref="canvasRef"
      :width="width"
      :height="height"
      class="signature-canvas"
      @mousedown="startDraw"
      @mousemove="draw"
      @mouseup="endDraw"
      @mouseleave="endDraw"
      @touchstart="startDraw"
      @touchmove="draw"
      @touchend="endDraw"
    />
    <button type="button" class="btn-clear-sig" @click="clear">
      <i class="pi pi-eraser"></i> Hapus
    </button>
  </div>
</template>

<style scoped>
.signature-canvas-wrap {
  display: inline-flex;
  flex-direction: column;
  gap: 8px;
}
.signature-canvas {
  border: 1px dashed var(--surface-border, #ccc);
  border-radius: 6px;
  cursor: crosshair;
  touch-action: none;
  max-width: 100%;
  background: #fff;
}
.btn-clear-sig {
  align-self: flex-end;
  padding: 4px 12px;
  font-size: 12px;
  border-radius: 4px;
  border: 1px solid var(--surface-border, #ccc);
  background: transparent;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.btn-clear-sig:hover {
  background: var(--surface-hover, #f5f5f5);
}
</style>
