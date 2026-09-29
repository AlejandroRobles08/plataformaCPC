<script setup>

import { ref, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({

  title: {

    type: String,

    default: 'Modal'

  },

  maxWidth: {

    type: String,

    default: 'max-w-xl'

  },

  resizable: {

    type: Boolean,

    default: false

  },

  width: {

    type: Number,

    default: 600

  },

  height: {

    type: Number,

    default: 400

  },

  minWidth: {

    type: Number,

    default: 350

  },

  minHeight: {

    type: Number,

    default: 250

  }

})

//referencia a próximo uso de las Props

const emit = defineEmits(['close'])

const modal = ref(null)

const position = ref({ x: 0, y: 0 })

const size = ref({ width: props.width, height: props.height })

const resizing = ref(false)

const resizeDirection = ref('')

const resizeStart = ref({ x: 0, y: 0, width: 0, height: 0, left: 0, top: 0 })

const dragging = ref(false)

const offset = ref({ x: 0, y: 0 })

// Centra el modal al abrirlo.

function centrarModal() {

  if (!modal.value) return

  const rect = modal.value.getBoundingClientRect()

  position.value = {

    x: Math.max(16, (window.innerWidth - rect.width) / 2),

    y: Math.max(16, (window.innerHeight - rect.height) / 2)

  }

}

// Inicia el arrastre del modal.

function iniciarArrastre(event) {

  if (event.button !== 0 || !modal.value || resizing.value) return

  const rect = modal.value.getBoundingClientRect()

  offset.value = {

    x: event.clientX - rect.left,

    y: event.clientY - rect.top

  }

  dragging.value = true

  document.addEventListener('pointermove', moverModal)

  document.addEventListener('pointerup', detenerArrastre)

}

// Mueve el modal mientras se arrastra.

function moverModal(event) {

  if (!dragging.value || !modal.value) return

  const rect = modal.value.getBoundingClientRect()

  const maxX = window.innerWidth - rect.width

  const maxY = window.innerHeight - rect.height

  const x = event.clientX - offset.value.x

  const y = event.clientY - offset.value.y

  position.value = {

    x: Math.min(Math.max(0, x), Math.max(0, maxX)),

    y: Math.min(Math.max(0, y), Math.max(0, maxY))

  }

}

// Centra el modal cuando se monta.

onMounted(() => {

  centrarModal()

})

// Finaliza el arrastre.

function detenerArrastre() {

  dragging.value = false

  document.removeEventListener('pointermove', moverModal)

  document.removeEventListener('pointerup', detenerArrastre)

}

//inicia el redimensionamiento

function iniciarResize(event, direction) {

  if (!props.resizable || event.button !== 0 || !modal.value) return

  event.stopPropagation()

  event.preventDefault()

  const rect = modal.value.getBoundingClientRect()

  resizeDirection.value = direction

  resizeStart.value = {

    x: event.clientX,

    y: event.clientY,

    width: rect.width,

    height: rect.height,

    left: rect.left,

    top: rect.top

  }

  resizing.value = true

  document.addEventListener(

    'pointermove',

    redimensionar

  )

  document.addEventListener(

    'pointerup',

    detenerResize

  )

}

//redimensionar el modal

function redimensionar(event) {

  if (!props.resizable || !resizing.value || !modal.value) return

  event.preventDefault()

  const start = resizeStart.value

  let width = start.width

  let height = start.height

  let left = start.left

  let top = start.top

  const dx = event.clientX - start.x

  const dy = event.clientY - start.y

  if (resizeDirection.value.includes('e')) {

    width = start.width + dx

  }

  if (resizeDirection.value.includes('s')) {

    height = start.height + dy

  }

  if (resizeDirection.value.includes('w')) {

    width = start.width - dx

    left = start.left + dx

  }

  if (resizeDirection.value.includes('n')) {

    height = start.height - dy

    top = start.top + dy

  }

  if (width < props.minWidth) {

    if (resizeDirection.value.includes('w')) {

      left = start.left + start.width - props.minWidth

    }

    width = props.minWidth

  }

  if (height < props.minHeight) {

    if (resizeDirection.value.includes('n')) {

      top = start.top + start.height - props.minHeight

    }

    height = props.minHeight

  }

  if (left < 0) {

    if (resizeDirection.value.includes('w')) {

      width += left

      left = 0

    } else {

      left = 0

    }

  }

  if (top < 0) {

    if (resizeDirection.value.includes('n')) {

      height += top

      top = 0

    } else {

      top = 0

    }

  }

  if (left + width > window.innerWidth) {

    if (resizeDirection.value.includes('e')) {

      width = window.innerWidth - left

    } else if (resizeDirection.value.includes('w')) {

      left = window.innerWidth - width

    }

  }

  if (top + height > window.innerHeight) {

    if (resizeDirection.value.includes('s')) {

      height = window.innerHeight - top

    } else if (resizeDirection.value.includes('n')) {

      top = window.innerHeight - height

    }

  }

  width = Math.max(props.minWidth, width)

  height = Math.max(props.minHeight, height)

  size.value = {

    width,

    height

  }

  position.value = {

    x: left,

    y: top

  }

}

// foinalizar el redimensionamiento

function detenerResize() {

  resizing.value = false

  resizeDirection.value = ''

  document.removeEventListener(

    'pointermove',

    redimensionar

  )

  document.removeEventListener(

    'pointerup',

    detenerResize

  )

}

//Limpia los eventos

onBeforeUnmount(() => {

  document.removeEventListener('pointermove', moverModal)

  document.removeEventListener('pointerup', detenerArrastre)

  document.removeEventListener('pointermove', redimensionar)

  document.removeEventListener('pointerup', detenerResize)

})

</script>

<template>

  <div class="fixed inset-0 z-40 bg-black/50">

    <div ref="modal"

      class="fixed z-50 rounded-lg bg-white shadow-xl"

      :class="!resizable ? `w-[calc(100%-2rem)] ${maxWidth}` : ''"

      :style="{

        ...(resizable ? {

          width: `${size.width}px`,

          height: `${size.height}px`

        } : {}),

        left: `${position.x}px`,

        top: `${position.y}px`,

        transform: 'none'

      }">

      <!-- Barra superior arrastrable -->

      <div class="flex cursor-move select-none items-center justify-between border-b p-5"

        @pointerdown="iniciarArrastre">

        <h2 class="text-xl font-bold text-gray-800">

          {{ title }}

        </h2>

        <button type="button"

          class="cursor-pointer text-2xl text-gray-400 hover:text-gray-700"

          @pointerdown.stop

          @click="emit('close')">

          ×

        </button>

      </div>

      <!-- Contenido del modal -->

      <div

        :class="resizable

          ? 'h-[calc(100%-73px)] overflow-y-auto p-5'

          : 'max-h-[80vh] overflow-y-auto p-5'"

      >

        <slot />

      </div>

      <!-- Esquinas del redimensionamiento -->

      <template v-if="resizable">

        <!-- Arriba -->

        <div class="absolute top-0 left-2 right-2 h-2 cursor-ns-resize"

          @pointerdown="e => iniciarResize(e, 'n')">

        </div>

        <!-- Abajo -->

        <div class="absolute bottom-0 left-2 right-2 h-2 cursor-ns-resize"

          @pointerdown="e => iniciarResize(e, 's')">

        </div>

        <!-- Izquierda -->

        <div class="absolute left-0 top-2 bottom-2 w-2 cursor-ew-resize"

          @pointerdown="e => iniciarResize(e, 'w')">

        </div>

        <!-- Derecha -->

        <div class="absolute right-0 top-2 bottom-2 w-2 cursor-ew-resize"

          @pointerdown="e => iniciarResize(e, 'e')">

        </div>

        <!-- Esquina superior izquierda -->

        <div class="absolute top-0 left-0 w-4 h-4 cursor-nwse-resize"

          @pointerdown="e => iniciarResize(e, 'nw')">

        </div>

        <!-- Esquina superior derecha -->

        <div class="absolute top-0 right-0 w-4 h-4 cursor-nesw-resize"

          @pointerdown="e => iniciarResize(e, 'ne')">

        </div>

        <!-- Esquina inferior izquierda -->

        <div class="absolute bottom-0 left-0 w-4 h-4 cursor-nesw-resize"

          @pointerdown="e => iniciarResize(e, 'sw')">

        </div>

        <!-- Esquina inferior derecha -->

        <div class="absolute bottom-0 right-0 w-4 h-4 cursor-nwse-resize"

          @pointerdown="e => iniciarResize(e, 'se')">

        </div>

      </template>

    </div>

  </div>

</template>