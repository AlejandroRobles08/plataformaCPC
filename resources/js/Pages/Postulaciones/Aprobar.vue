<script setup>
import { computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import DraggableModal from '@/Components/DraggableModal.vue'

const props = defineProps({
  postulacion: { type: Object, required: true },
  // Solo consejos con lugar; cada uno trae la ocupación de sus fórmulas: { formula: total }
  consejos: { type: Array, default: () => [] }
})

// Deben coincidir con Integrante::MAX_FORMULAS e INTEGRANTES_POR_FORMULA
const MAX_FORMULAS = 15
const INTEGRANTES_POR_FORMULA = 2

const emit = defineEmits(['close'])

const form = useForm({
  fecha_validacion: '',
  acta_resolucion: null,
  // Si la persona eligió consejo en su postulación, queda fijo en ese
  consejo_id: props.postulacion.consejo_solicitado_id ?? '',
  formula: ''
})

const consejoFijo = computed(() => props.postulacion.consejo_solicitado_id != null)

const consejoElegido = computed(() => props.consejos.find(c => c.id === form.consejo_id))

// Solo las fórmulas del consejo elegido con lugar disponible, con su ocupación
const formulas = computed(() => {
  if (!consejoElegido.value) return []
  return Array.from({ length: MAX_FORMULAS }, (_, i) => {
    const numero = i + 1
    return { numero, total: consejoElegido.value.ocupacion?.[numero] ?? 0 }
  }).filter(f => f.total < INTEGRANTES_POR_FORMULA)
})

// Al cambiar de consejo, la fórmula elegida ya no aplica
watch(() => form.consejo_id, () => { form.formula = '' })

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
        <!-- Consejo al que se postuló: el select solo ofrece ese consejo -->
        <template v-if="consejoFijo">
          <select v-model="form.consejo_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            <option :value="postulacion.consejo_solicitado_id" :disabled="!consejoElegido">
              {{ postulacion.consejo_solicitado?.nombre }}{{ consejoElegido ? '' : ' (sin lugares disponibles)' }}
            </option>
          </select>
          <p v-if="!consejoElegido" class="text-sm text-red-600 mt-1">
            El consejo al que se postuló ya no tiene lugares disponibles.
          </p>
          <p v-else class="text-xs text-gray-500 mt-1">Es el consejo que la persona eligió al postularse.</p>
        </template>
        <select v-else v-model="form.consejo_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
          <option value="">Seleccione un consejo</option>
          <option v-for="consejo in consejos" :key="consejo.id" :value="consejo.id">
            {{ consejo.nombre }}
          </option>
        </select>
        <p v-if="!consejoFijo && !consejos.length" class="text-xs text-gray-500 mt-1">
          No hay consejos con lugares disponibles.
        </p>
        <div v-if="form.errors.consejo_id" class="text-red-500 text-sm">
          {{ form.errors.consejo_id }}
        </div>
      </div>

      <!-- Fórmula -->
      <div>
        <label class="block text-sm font-medium text-gray-700">
          Fórmula
        </label>
        <select v-model="form.formula" required :disabled="!consejoElegido"
          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm disabled:bg-gray-100 disabled:cursor-not-allowed">
          <option value="" disabled>
            {{ consejoElegido ? 'Seleccione una fórmula' : 'Primero seleccione un consejo' }}
          </option>
          <option v-for="f in formulas" :key="f.numero" :value="f.numero">
            Fórmula {{ f.numero }} ({{ f.total }}/{{ INTEGRANTES_POR_FORMULA }})
          </option>
        </select>
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
        <button type="submit" :disabled="form.processing || !consejoElegido"
          class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50">
          Aprobar
        </button>
      </div>
    </form>
  </DraggableModal>
</template>
