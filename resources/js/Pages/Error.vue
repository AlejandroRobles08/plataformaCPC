<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'

// Página que se muestra cuando ocurre un error HTTP (ver app/Exceptions/FriendlyErrorResponse.php).
// No usa AuthenticatedLayout porque en un 404 de ruta inexistente no hay props compartidas.
const props = defineProps({
  status: { type: Number, required: true },
  titulo: { type: String, required: true },
  mensaje: { type: String, required: true },
  sugerencia: { type: String, default: null },
  autenticado: { type: Boolean, default: false },
})

const icono = computed(() => ({
  403: 'lock',
  404: 'search_off',
  413: 'upload_file',
  419: 'schedule',
  429: 'hourglass_top',
  503: 'construction',
}[props.status] ?? 'error'))

// Tras una sesión expirada conviene recargar en lugar de regresar
const puedeRecargar = computed(() => [419, 429, 500, 503].includes(props.status))

const regresar = () => {
  if (window.history.length > 1) {
    window.history.back()
  } else {
    window.location.href = '/'
  }
}

const recargar = () => window.location.reload()
</script>

<template>
  <Head :title="titulo" />

  <div class="flex items-center justify-center min-h-screen p-6 bg-gray-50">
    <div class="w-full max-w-lg p-8 text-center bg-white rounded-lg shadow-xl sm:p-10">
      <div class="flex items-center justify-center w-16 h-16 mx-auto mb-5 rounded-full bg-green-50">
        <span class="material-symbols-outlined text-green-700 !text-4xl">{{ icono }}</span>
      </div>

      <h1 class="text-2xl font-semibold text-gray-800">{{ titulo }}</h1>
      <p class="mt-3 text-gray-600">{{ mensaje }}</p>

      <p v-if="sugerencia" class="px-4 py-3 mt-5 text-sm text-left text-gray-600 rounded-md bg-gray-50">
        {{ sugerencia }}
      </p>

      <div class="flex flex-col justify-center gap-3 mt-8 sm:flex-row">
        <button
          v-if="puedeRecargar"
          type="button"
          class="px-5 py-2 text-sm font-medium text-white bg-green-700 rounded-md hover:bg-green-800"
          @click="recargar"
        >
          Volver a intentar
        </button>
        <button
          v-else
          type="button"
          class="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
          @click="regresar"
        >
          Regresar
        </button>

        <Link
          :href="autenticado ? route('dashboard') : route('login')"
          class="px-5 py-2 text-sm font-medium rounded-md"
          :class="puedeRecargar
            ? 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50'
            : 'text-white bg-green-700 hover:bg-green-800'"
        >
          {{ autenticado ? 'Ir al inicio' : 'Iniciar sesión' }}
        </Link>
      </div>

      <p class="mt-8 text-xs text-gray-400">Código de error: {{ status }}</p>
    </div>
  </div>
</template>
