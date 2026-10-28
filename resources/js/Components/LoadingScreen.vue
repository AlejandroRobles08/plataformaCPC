<script>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

// Estado compartido a nivel de módulo: el layout se vuelve a montar en cada
// página, así que los listeners se registran una sola vez aquí.
const loading = ref(false)
const DELAY_MS = 250

let currentVisit = null
let timer = null

router.on('start', (event) => {
  const visit = event.detail.visit
  // Mismo criterio que la barra de progreso de Inertia (ignora visitas async/polling)
  if (!visit.showProgress) return

  currentVisit = visit
  clearTimeout(timer)
  timer = setTimeout(() => {
    loading.value = true
  }, DELAY_MS)
})

router.on('finish', (event) => {
  // Si una visita interrumpe a otra, solo la última debe ocultar el loader
  if (event.detail.visit !== currentVisit) return

  currentVisit = null
  clearTimeout(timer)
  loading.value = false
})
</script>

<!-- Necesario para que el template tenga acceso a `loading` del bloque anterior -->
<script setup>
</script>

<template>
  <Transition
    enter-active-class="transition-opacity duration-150"
    enter-from-class="opacity-0"
    leave-active-class="transition-opacity duration-150"
    leave-to-class="opacity-0"
  >
    <div
      v-if="loading"
      class="absolute inset-0 z-[60] flex items-center justify-center bg-gray-50/80 backdrop-blur-[1px]"
      role="status"
      aria-live="polite"
    >
      <div class="flex flex-col items-center gap-3">
        <div class="h-10 w-10 animate-spin rounded-full border-4 border-gray-200 border-t-green-700"></div>
        <span class="text-sm font-medium text-gray-600">Cargando…</span>
      </div>
    </div>
  </Transition>
</template>
