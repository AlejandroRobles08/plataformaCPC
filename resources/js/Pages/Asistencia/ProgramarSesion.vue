<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import DraggableModal from '@/Components/DraggableModal.vue'

const props = defineProps({
  consejoId: {
    type: [Number, String],
    required: true,
  },
  fecha: {
    type: String,
    required: true,
  },
  // Tipos ya programados ese día (al agregar una sesión adicional no se pueden repetir)
  tiposOcupados: {
    type: Array,
    default: () => [],
  },
  // true cuando se programa la segunda sesión del día
  adicional: {
    type: Boolean,
    default: false,
  },
})

const tiposSesion = [
  { value: 'ordinaria', label: 'Ordinaria' },
  { value: 'solemne', label: 'Solemne' },
  { value: 'extraordinaria', label: 'Extraordinaria' },
]

const emit = defineEmits(['close', 'saved'])

// Datos de la programación
const form = ref({
  fecha: props.fecha,
  tipo_sesion: '',
})

// Errores de validación
const errors = ref({})
const loading = ref(false)

// Actualizar fecha si cambia desde Calendar.vue
watch(
  () => props.fecha,
  (value) => {
    form.value.fecha = value
  }
)

// Cerrar modal
function cerrarModal() {
  if (!loading.value) {
    emit('close')
  }
}

// Programar sesión
function submitForm() {
  errors.value = {}

  if (!form.value.tipo_sesion) {
    errors.value.tipo_sesion = 'Seleccione el tipo de sesión.'
    return
  }

  loading.value = true

  router.post(
    route('asistencias.sesiones.programar', {
      consejo: props.consejoId,
    }),
    {
      fecha: form.value.fecha,
      tipo_sesion: form.value.tipo_sesion,
    },
    {
      preserveScroll: true,

      onSuccess: () => {
        Swal.fire({
          icon: 'success',
          title: 'Sesión programada',
          text: 'La sesión se programó correctamente.',
          confirmButtonColor: '#374151',
          confirmButtonText: 'Aceptar',
        })

        emit('saved')
        emit('close')
      },

      onError: (serverErrors) => {
        errors.value = serverErrors
      },

      onFinish: () => {
        loading.value = false
      },
    }
  )
}
</script>

<template>
  <DraggableModal :title="adicional ? 'Programar sesión adicional' : 'Programar sesión'" max-width="max-w-md"
    @close="cerrarModal">
      <!-- Aviso de sesión adicional -->
      <div v-if="adicional"
        class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        Ya existe una sesión programada para esta fecha. Esta será la segunda sesión del día.
      </div>

      <!-- Fecha -->
      <div class="mb-4">
        <label class="mb-1 block text-sm font-medium text-gray-700">
          Fecha
        </label>
        <input
          v-model="form.fecha"
          type="date"
          readonly
          class="w-full rounded border border-gray-300 bg-gray-100 px-3 py-2 text-gray-700"/>
        <p v-if="errors.fecha" class="mt-1 text-sm text-red-600">
          {{ errors.fecha }}
        </p>
      </div>

      <!-- Tipo de sesión -->
      <div class="mb-6">
        <label class="mb-1 block text-sm font-medium text-gray-700">
          Tipo de sesión
        </label>

        <select v-model="form.tipo_sesion"
          class="w-full rounded border border-gray-300 px-3 
          py-2 focus:border-gray-600 focus:outline-none">
          <option value="" disabled>
            Seleccione el tipo de sesión
          </option>

          <option v-for="tipo in tiposSesion" :key="tipo.value"
            :value="tipo.value"
            :disabled="tiposOcupados.includes(tipo.value)">
            {{ tipo.label }}{{ tiposOcupados.includes(tipo.value) ? ' (ya programada este día)' : '' }}
          </option>
        </select>

        <p v-if="errors.tipo_sesion" class="mt-1 text-sm text-red-600">
          {{ errors.tipo_sesion }}
        </p>
      </div>

      <!-- Botones -->
      <div class="flex justify-end gap-3">
        <button type="button"
          class="rounded bg-red-300 px-4 py-2 text-white transition hover:bg-red-500 
          disabled:opacity-50"
          :disabled="loading"@click="cerrarModal">
          Cancelar
        </button>

        <button type="button"
          class="rounded bg-green-700 px-4 py-2 text-white transition hover:bg-green-800 disabled:opacity-50"
          :disabled="loading || !form.tipo_sesion"@click="submitForm">
          {{ loading ? 'Programando...' : 'Programar sesión' }}
        </button>
      </div>
  </DraggableModal>
</template>