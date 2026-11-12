<script setup>
/**
 * Indicador global de peticiones lentas.
 *
 * Se monta una sola vez en la raíz de la app (app.js), así que funciona en
 * todos los layouts. Escucha:
 *   - Visitas de Inertia (router.get/post/put/patch/delete, <Link>, useForm).
 *   - Peticiones hechas con axios (window.axios / import axios).
 *
 * Solo aparece si la petición tarda más de SHOW_AFTER_MS; las rápidas no
 * muestran nada. Para excluir una petición axios concreta:
 *   axios.get(url, { silent: true })
 */
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const SHOW_AFTER_MS = 600      // umbral para considerar "lenta" una petición
const LABEL_AFTER_MS = 2500    // a partir de aquí se muestra el texto "Procesando…"
const MIN_VISIBLE_MS = 300     // evita parpadeo si termina justo al aparecer

const pending = ref(0)
const visible = ref(false)
const showLabel = ref(false)
const method = ref('get')

let showTimer = null
let labelTimer = null
let hideTimer = null
let shownAt = 0

const label = computed(() => (method.value === 'get' ? 'Cargando…' : 'Procesando…'))

function begin(httpMethod = 'get') {
  pending.value++
  method.value = String(httpMethod).toLowerCase()
  clearTimeout(hideTimer)

  if (pending.value === 1 && !visible.value) {
    showTimer = setTimeout(() => {
      visible.value = true
      shownAt = Date.now()
    }, SHOW_AFTER_MS)
    labelTimer = setTimeout(() => {
      showLabel.value = true
    }, LABEL_AFTER_MS)
  }
}

function end() {
  if (pending.value === 0) return
  pending.value--
  if (pending.value > 0) return

  clearTimeout(showTimer)
  clearTimeout(labelTimer)

  if (!visible.value) {
    showLabel.value = false
    return
  }

  const wait = Math.max(0, MIN_VISIBLE_MS - (Date.now() - shownAt))
  hideTimer = setTimeout(() => {
    visible.value = false
    showLabel.value = false
  }, wait)
}

// --- Inertia -------------------------------------------------------------
const trackedVisits = new WeakSet()
const unsubscribers = []

// --- axios ---------------------------------------------------------------
let reqInterceptor = null
let resInterceptor = null

const isTrackedAxios = (config) =>
  config && !config.silent && !config.headers?.['X-Inertia']

onMounted(() => {
  unsubscribers.push(
    router.on('start', (event) => {
      const visit = event.detail.visit
      // Ignora polling, recargas async y prefetch (igual que la barra de Inertia)
      if (!visit.showProgress || visit.prefetch) return
      trackedVisits.add(visit)
      begin(visit.method)
    }),
    router.on('finish', (event) => {
      const visit = event.detail.visit
      if (!trackedVisits.has(visit)) return
      trackedVisits.delete(visit)
      end()
    }),
  )

  // Inertia también usa axios internamente: esas peticiones se excluyen por el
  // header X-Inertia para no contarlas dos veces.
  reqInterceptor = axios.interceptors.request.use((config) => {
    if (isTrackedAxios(config)) {
      config.__slowTracked = true
      begin(config.method)
    }
    return config
  })

  const finishAxios = (config) => {
    if (config?.__slowTracked) {
      config.__slowTracked = false
      end()
    }
  }

  resInterceptor = axios.interceptors.response.use(
    (response) => {
      finishAxios(response.config)
      return response
    },
    (error) => {
      finishAxios(error.config)
      return Promise.reject(error)
    },
  )
})

onBeforeUnmount(() => {
  unsubscribers.forEach((off) => off())
  axios.interceptors.request.eject(reqInterceptor)
  axios.interceptors.response.eject(resInterceptor)
  clearTimeout(showTimer)
  clearTimeout(labelTimer)
  clearTimeout(hideTimer)
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      leave-active-class="transition-opacity duration-300"
      leave-to-class="opacity-0"
    >
      <div v-if="visible" role="status" aria-live="polite" class="pointer-events-none">
        <!-- Barra superior indeterminada -->
        <div class="fixed inset-x-0 top-0 z-[9999] h-[3px] overflow-hidden bg-green-700/10">
          <div class="slow-bar h-full w-1/3 rounded-full bg-green-700"></div>
        </div>

        <!-- Píldora discreta, solo si la espera se alarga -->
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="translate-y-2 opacity-0"
          leave-active-class="transition duration-150 ease-in"
          leave-to-class="translate-y-2 opacity-0"
        >
          <div
            v-if="showLabel"
            class="fixed bottom-5 right-5 z-[9999] flex items-center gap-2 rounded-full bg-white/95 px-4 py-2 text-sm font-medium text-gray-600 shadow-lg ring-1 ring-gray-200"
          >
            <span class="flex gap-1">
              <span class="slow-dot h-1.5 w-1.5 rounded-full bg-green-700"></span>
              <span class="slow-dot h-1.5 w-1.5 rounded-full bg-green-700 [animation-delay:150ms]"></span>
              <span class="slow-dot h-1.5 w-1.5 rounded-full bg-green-700 [animation-delay:300ms]"></span>
            </span>
            {{ label }}
          </div>
        </Transition>

        <span class="sr-only">{{ label }}</span>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.slow-bar {
  animation: slow-bar-slide 1.2s ease-in-out infinite;
}

.slow-dot {
  animation: slow-dot-pulse 0.9s ease-in-out infinite;
}

@keyframes slow-bar-slide {
  0%   { transform: translateX(-100%); }
  100% { transform: translateX(300%); }
}

@keyframes slow-dot-pulse {
  0%, 80%, 100% { opacity: 0.25; transform: scale(0.8); }
  40%           { opacity: 1;    transform: scale(1); }
}

@media (prefers-reduced-motion: reduce) {
  .slow-bar  { animation-duration: 3s; }
  .slow-dot  { animation: none; opacity: 0.7; }
}
</style>
