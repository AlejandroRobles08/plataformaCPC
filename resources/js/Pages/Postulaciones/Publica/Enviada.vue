<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import { CheckCircleIcon, ClipboardDocumentIcon, ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  folio: { type: String, required: true },
  nombre: { type: String, default: '' },
  correo: { type: String, default: '' },
  consejo: { type: String, default: null },
})

const copiado = ref(false)

const copiarFolio = async () => {
  try {
    await navigator.clipboard.writeText(props.folio)
    copiado.value = true
    setTimeout(() => (copiado.value = false), 2000)
  } catch {
    // Sin permiso de portapapeles: el folio sigue visible para copiarlo a mano
  }
}
</script>

<template>
  <PublicLayout>
    <Head title="Postulación enviada" />

    <section class="max-w-2xl mx-auto bg-white rounded-lg shadow-sm px-6 py-10 sm:px-10 text-center">
      <div class="mx-auto flex items-center justify-center w-14 h-14 rounded-full bg-green-100">
        <CheckCircleIcon class="w-8 h-8 text-green-700" aria-hidden="true" />
      </div>

      <h1 class="mt-4 text-2xl font-bold text-gray-800">¡Gracias, {{ nombre }}!</h1>
      <p class="mt-2 text-gray-600">
        Recibimos tu postulación<template v-if="consejo"> para el <span class="font-medium text-gray-800">{{ consejo }}</span></template>.
      </p>

      <div class="mt-6 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-5">
        <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Tu folio</p>
        <div class="mt-1 flex items-center justify-center gap-2">
          <span class="text-2xl font-mono font-bold text-gray-900 select-all">{{ folio }}</span>
          <button type="button" @click="copiarFolio" class="p-1 rounded text-gray-500 hover:text-gray-800"
            :aria-label="copiado ? 'Folio copiado' : 'Copiar folio'">
            <ClipboardDocumentCheckIcon v-if="copiado" class="w-5 h-5 text-green-700" aria-hidden="true" />
            <ClipboardDocumentIcon v-else class="w-5 h-5" aria-hidden="true" />
          </button>
        </div>
        <p class="mt-2 text-xs text-gray-500">Guárdalo o tómale captura: lo necesitarás para cualquier aclaración.</p>
      </div>

      <div class="mt-6 text-left text-sm text-gray-600 space-y-2">
        <p class="font-semibold text-gray-800">¿Qué sigue?</p>
        <ul class="list-disc list-inside space-y-1">
          <li>El equipo de los consejos revisará tu postulación.</li>
          <li>Te escribiremos a <span class="font-medium text-gray-800 break-all">{{ correo }}</span> con el resultado.</li>
          <li>Si tu postulación avanza, recibirás un acceso a la plataforma para subir tus documentos.</li>
        </ul>
      </div>
    </section>
  </PublicLayout>
</template>
