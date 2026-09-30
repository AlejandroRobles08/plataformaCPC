<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

// Propiedades configurables del modal
const props = defineProps({
  title: { type: String, default: 'Modal' },         
  maxWidth: { type: String, default: 'max-w-xl' },
  resizable: { type: Boolean, default: true },     
  width: { type: Number, default: null },          
  height: { type: Number, default: null },         
  minWidth: { type: Number, default: 320 },        
  minHeight: { type: Number, default: 200 }        
})
const emit = defineEmits(['close'])

// Estado interno
const modal = ref(null)                                                       
const position = ref({ x: 0, y: 0 })                                           
const size = ref(props.width && props.height ? 
{ width: props.width, height: props.height } : null)
const dragging = ref(false)                                                    
const offset = ref({ x: 0, y: 0 })                                             
const resizing = ref(false)                                                    
const resizeDirection = ref('') //dirección activa: n, s, e, w, ne, nw, se, sw
const resizeStart = ref({ x: 0, y: 0, width: 0, height: 0, left: 0, top: 0 }) //medidas al iniciar el redimensionado

//manejo de redimensionado: dirección y clases de posición/cursor
const handles = [
  { dir: 'n', cls: 'top-0 left-3 right-3 h-1.5 cursor-ns-resize' },
  { dir: 's', cls: 'bottom-0 left-3 right-3 h-1.5 cursor-ns-resize' },
  { dir: 'w', cls: 'left-0 top-3 bottom-3 w-1.5 cursor-ew-resize' },
  { dir: 'e', cls: 'right-0 top-3 bottom-3 w-1.5 cursor-ew-resize' },
  { dir: 'nw', cls: 'top-0 left-0 h-3 w-3 cursor-nwse-resize' },
  { dir: 'ne', cls: 'top-0 right-0 h-3 w-3 cursor-nesw-resize' },
  { dir: 'sw', cls: 'bottom-0 left-0 h-3 w-3 cursor-nesw-resize' },
  { dir: 'se', cls: 'bottom-0 right-0 h-3 w-3 cursor-nwse-resize' }
]

//limita un valor entre un mínimo y un máximo (el mínimo siempre gana)
const clamp = (v, min, max) => Math.min(Math.max(v, min), Math.max(min, max))

//centra el modal en la ventana al abrirlo
function centrarModal() {
  if (!modal.value) return
  const rect = modal.value.getBoundingClientRect()
  position.value = { x: Math.max(16, (window.innerWidth - rect.width) / 2), y: Math.max(16, (window.innerHeight - rect.height) / 2) }
}

//inicia el arrastre desde la barra superior
function iniciarArrastre(event) {
  if (event.button !== 0 || !modal.value || resizing.value) return
  const rect = modal.value.getBoundingClientRect()
  offset.value = { x: event.clientX - rect.left, y: event.clientY - rect.top }
  dragging.value = true
  document.addEventListener('pointermove', moverModal)
  document.addEventListener('pointerup', detenerArrastre)
}

//mueve el modal siguiendo al puntero sin salirse de la ventana
function moverModal(event) {
  if (!dragging.value || !modal.value) return
  const rect = modal.value.getBoundingClientRect()
  position.value = {
    x: clamp(event.clientX - offset.value.x, 0, window.innerWidth - rect.width),
    y: clamp(event.clientY - offset.value.y, 0, window.innerHeight - rect.height)
  }
}

//finaliza el arrastre y libera los listeners
function detenerArrastre() {
  dragging.value = false
  document.removeEventListener('pointermove', moverModal)
  document.removeEventListener('pointerup', detenerArrastre)
}

//inicia el redimensionado guardando las medidas actuales del modal
function iniciarResize(event, direction) {
  if (!props.resizable || event.button !== 0 || !modal.value) return
  event.stopPropagation(); event.preventDefault()   // Evita iniciar el arrastre y la selección de texto
  const rect = modal.value.getBoundingClientRect()
  resizeStart.value = { x: event.clientX, y: event.clientY, width: rect.width, height: rect.height, left: rect.left, top: rect.top }
  size.value = { width: rect.width, height: rect.height } // Congela el tamaño actual en px
  resizeDirection.value = direction
  resizing.value = true
  document.body.style.userSelect = 'none'            // Bloquea selección mientras se redimensiona
  document.addEventListener('pointermove', redimensionar)
  document.addEventListener('pointerup', detenerResize)
}

//calcula el nuevo tamaño/posición según la dirección, respetando mínimos y límites de la ventana
function redimensionar(event) {
  if (!resizing.value) return
  const s = resizeStart.value, d = resizeDirection.value
  const dx = event.clientX - s.x, dy = event.clientY - s.y
  let { width, height, left, top } = s
  if (d.includes('e')) width = clamp(s.width + dx, props.minWidth, window.innerWidth - s.left)                                     // Borde derecho: crece hacia la derecha
  if (d.includes('s')) height = clamp(s.height + dy, props.minHeight, window.innerHeight - s.top)                                  // Borde inferior: crece hacia abajo
  if (d.includes('w')) { width = clamp(s.width - dx, props.minWidth, s.left + s.width); left = s.left + s.width - width }          // Borde izquierdo: mantiene fijo el derecho
  if (d.includes('n')) { height = clamp(s.height - dy, props.minHeight, s.top + s.height); top = s.top + s.height - height }       // Borde superior: mantiene fijo el inferior
  size.value = { width, height }
  position.value = { x: left, y: top }
}

//finaliza el redimensionado y libera los listeners
function detenerResize() {
  resizing.value = false
  resizeDirection.value = ''
  document.body.style.userSelect = ''
  document.removeEventListener('pointermove', redimensionar)
  document.removeEventListener('pointerup', detenerResize)
}


onMounted(centrarModal)
onBeforeUnmount(() => { detenerArrastre(); detenerResize() })
</script>

<template>
  <div class="fixed inset-0 z-40 bg-black/50">
    <div ref="modal" class="fixed z-50 flex flex-col rounded-lg bg-white shadow-xl"
      :class="size ? '' : `w-[calc(100%-2rem)] ${maxWidth}`"
      :style="{ left: `${position.x}px`, top: `${position.y}px`,
       ...(size ? { width: `${size.width}px`, height: `${size.height}px` } : {}) }">
      <!-- Barra superior arrastrable -->
      <div class="flex shrink-0 cursor-move select-none items-center justify-between border-b p-5"
       @pointerdown="iniciarArrastre">
        <h2 class="text-xl font-bold text-gray-800">{{ title }}</h2>
        <button type="button" class="cursor-pointer text-2xl text-gray-400 hover:text-gray-700" 
        @pointerdown.stop @click="emit('close')">×</button>
      </div>
      <!-- Contenido: ocupa el espacio restante y hace scroll si no cabe -->
      <div class="min-h-0 flex-1 overflow-y-auto p-5" :class="{ 'max-h-[80vh]': !size }"><slot /></div>
      <!-- Handles invisibles de redimensionado en bordes y esquinas -->
      <template v-if="resizable">
        <div v-for="h in handles" :key="h.dir" class="absolute z-10" :class="h.cls" @pointerdown="e => iniciarResize(e, h.dir)" />
        <!-- Indicador visual en la esquina inferior derecha -->
        <svg class="pointer-events-none absolute bottom-1 right-1 h-3 w-3 text-gray-400" viewBox="0 0 10 10"><path d="M9 1L1 9M9 5L5 9" stroke="currentColor" stroke-width="1.2" /></svg>
      </template>
    </div>
  </div>
</template>
