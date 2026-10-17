<script setup>
import { useForm } from '@inertiajs/vue3'
import DraggableModal from '@/Components/DraggableModal.vue'

const props = defineProps({
  postulacion: { type: Object, required: true },
  consejos: { type: Array, default: () => [] }
})

const emit = defineEmits(['close'])

const form = useForm({
  fecha_validacion: '',
  acta_resolucion: null,
  consejo_id: ''
})

const submit = () => {
  form.post(route('postulaciones.aprobar', props.postulacion.id), {
    forceFormData: true,
    preserveScroll: true,
    // La alerta de éxito la muestra el panel con el mensaje flash
    onSuccess: () => {
      form.reset()
      emit('close')
    }
  })
}
</script>

<template>
  <DraggableModal title="Aprobar postulación" max-width="max-w-lg" @close="emit('close')">
    <form @submit.prevent="submit" class="space-y-4">

      <p class="text-sm text-gray-600">
        Postulante:
        <span class="font-semibold text-gray-800">
          {{ postulacion.nombre }} {{ postulacion.apellido_paterno }} {{ postulacion.apellido_materno }}
        </span>
      </p>

      <!-- Fecha de resolución -->
      <div>
        <label class="block text-sm font-medium text-gray-700">
          Fecha de resolución
        </label>
        <input v-model="form.fecha_validacion" type="date" required
          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
        <div v-if="form.errors.fecha_validacion" class="text-red-500 text-sm">
          {{ form.errors.fecha_validacion }}
        </div>
      </div>

      <!-- Acta de resolución -->
      <div>
        <label class="block text-sm font-medium text-gray-700">
          Acta de resolución (PDF)
        </label>
        <input type="file" accept="application/pdf" required
          @change="e => form.acta_resolucion = e.target.files[0]" class="block w-full mt-1" />
        <div v-if="form.errors.acta_resolucion" class="text-red-500 text-sm">
          {{ form.errors.acta_resolucion }}
        </div>
      </div>

      <!-- Consejo -->
      <div>
        <label class="block text-sm font-medium text-gray-700">
          Consejo donde se registrará el integrante
        </label>
        <select v-model="form.consejo_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
          <option value="">Seleccione un consejo</option>
          <option v-for="consejo in consejos" :key="consejo.id" :value="consejo.id">
            {{ consejo.nombre }}
          </option>
        </select>
        <p class="text-xs text-gray-500 mt-1">
          La fórmula se asigna automáticamente según el orden de alta en el consejo.
        </p>
        <div v-if="form.errors.consejo_id" class="text-red-500 text-sm">
          {{ form.errors.consejo_id }}
        </div>
        <div v-if="form.errors.formula" class="text-red-500 text-sm">
          {{ form.errors.formula }}
        </div>
      </div>

      <!-- Botones -->
      <div class="flex justify-end space-x-2 pt-4">
        <button type="button" @click="emit('close')"
          class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
          Cancelar
        </button>
        <button type="submit" :disabled="form.processing"
          class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50">
          Aprobar
        </button>
      </div>
    </form>
  </DraggableModal>
</template>
