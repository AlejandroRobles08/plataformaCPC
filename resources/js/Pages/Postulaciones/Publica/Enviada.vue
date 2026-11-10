<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { CheckCircleIcon, EnvelopeIcon } from '@heroicons/vue/24/outline'

defineProps({
  nombre: { type: String, default: '' },
  correo: { type: String, default: '' },
  consejo: { type: String, default: null },
})
</script>

<template>
  <PublicLayout>
    <Head title="Postulación enviada" />

    <section class="max-w-2xl mx-auto bg-white rounded-lg shadow-sm px-6 py-10 sm:px-10 text-center">
      <div class="mx-auto flex items-center justify-center w-14 h-14 rounded-full bg-green-100">
        <CheckCircleIcon class="w-8 h-8 text-green-700" aria-hidden="true" />
      </div>

      <h1 class="mt-4 text-2xl font-bold text-gray-800">¡Postulación creada con éxito!</h1>
      <p class="mt-2 text-gray-600">
        Gracias, {{ nombre }}. Recibimos su postulación<template v-if="consejo"> para el
        <span class="font-medium text-gray-800">{{ consejo }}</span></template>.
      </p>

      <div class="mt-6 flex gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-5 text-left">
        <EnvelopeIcon class="w-6 h-6 shrink-0 text-green-700" aria-hidden="true" />
        <div class="text-sm text-green-900">
          <p class="font-semibold">Le enviamos un correo de confirmación</p>
          <p class="mt-1">
            Lo enviamos a <span class="font-medium break-all">{{ correo }}</span>.
            Si no lo encuentra en su bandeja de entrada, revise la carpeta de correo no deseado (spam).
          </p>
        </div>
      </div>

      <div class="mt-6 text-left text-sm text-gray-600 space-y-2">
        <p class="font-semibold text-gray-800">¿Qué sigue?</p>
        <ul class="list-disc list-inside space-y-1">
          <li>El equipo de los consejos revisará su postulación.</li>
          <li>Le escribiremos a ese mismo correo con el resultado.</li>
          <li>Si su postulación avanza, recibirá un acceso a la plataforma para subir sus documentos.</li>
        </ul>
      </div>
    </section>
  </PublicLayout>
</template>
