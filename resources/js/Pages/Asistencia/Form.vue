<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import DraggableModal from '@/Components/DraggableModal.vue'

const props = defineProps({
  consejoId: {type: [Number, String],required: true},
  integrantes: {type: Array, required: true},
  sesion: {type: Object, required: true},
  asistencias: {type: Array, default: () => []}
})

const emit = defineEmits(['close', 'saved'])

const page = usePage()

// Solo el super_admin puede modificar un pase de lista ya guardado
const esSuperAdmin = computed(() =>
  (page.props.auth.roles ?? []).includes('super_admin')
)

// El pase de lista ya se envió si la sesión tiene asistencias guardadas
const listaGuardada = computed(() => props.asistencias.length > 0)

// Evidencia actual de la sesión (todas las asistencias guardan la misma)
const evidenciaActual = computed(() =>
  props.asistencias.find(a => a.evidencia)?.evidencia ?? null
)

// Admin con la lista ya guardada: solo consulta
const soloLectura = computed(() => listaGuardada.value && !esSuperAdmin.value)

// Datos de asistencia.
const form = ref({
  asistencias: props.integrantes.map( i => {
    const asistenciaExistente = props.asistencias.find(a=> Number(a.integrante_id) === Number(i.id))
    return {
      integrante_id: i.id,
      estado: asistenciaExistente?.estado ?? 'asistio'
    }
  })
})

const evidencia = ref(null)
const errorArchivo = ref('')
const errores = ref({})
const procesando = ref(false)

// Valida que la evidencia sea un PDF.
function onFileChange(e) {
  const file = e.target.files[0]

  if (!file) {
    evidencia.value = null
    errorArchivo.value = ''
    return
  }

  if (file.type !== 'application/pdf') {
    errorArchivo.value = 'Solo se permiten archivos PDF.'
    evidencia.value = null
    e.target.value = ''
    return
  }

  errorArchivo.value = ''
  evidencia.value = file
}

// Guarda las asistencias de la sesión.
function submitForm() {
  if (procesando.value || soloLectura.value) return

  errores.value = {}
  const formData = new FormData()

  // Datos de la sesión.
  formData.append('fecha', props.sesion.fecha)
  formData.append('tipo_sesion', props.sesion.tipo_sesion)

  // Asistencias.
  form.value.asistencias.forEach((a, index) => {
    formData.append(
      `asistencias[${index}][integrante_id]`,
      a.integrante_id
    )

    formData.append(
      `asistencias[${index}][estado]`,
      a.estado
    )
  })

  // Evidencia.
  if (evidencia.value) {
    formData.append('evidencia', evidencia.value)
  }
  procesando.value = true
  router.post(
    route('asistencias.sesion.store', props.consejoId),
    formData,
    {
      forceFormData: true,
      preserveScroll: true,

      onSuccess: () => {
        Swal.fire({
          icon: 'success',
          title: '¡Asistencia guardada!',
          text: 'El pase de lista se registró correctamente.',
          confirmButtonText: 'Aceptar',
          confirmButtonColor: '#374151'
        }).then(() => {
          emit('saved')
          emit('close')
        })
      },
      // Errores de validación del backend (evidencia obligatoria, lista ya guardada, etc.)
      onError: (errors) => {
        errores.value = errors
      },
      onFinish: () => {
        procesando.value = false
      }
    }
  )
}
</script>

<template>
  <DraggableModal :title="`Registrar asistencia - ${sesion.fecha}`"
    max-width="max-w-2xl" @close="emit('close')">
    <!-- Pase de lista ya enviado -->
    <div v-if="listaGuardada"
      class="mb-4 rounded-lg border border-green-200 bg-green-100 px-4 py-3 text-green-800">
      Lista de asistencia guardada correctamente.
    </div>

    <!-- Datos de la sesión -->
    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
      <!-- Fecha -->
      <div>
        <label class="mb-1 block text-sm font-medium">
          Fecha
        </label>

        <input type="date" :value="sesion.fecha"
          readonly class="w-full rounded border bg-gray-100 px-3 py-2"/>
      </div>

      <!-- Tipo de sesión -->
      <div>
        <label class="mb-1 block text-sm font-medium">
          Tipo de sesión
        </label>

        <input type="text" :value="sesion.tipo_sesion"
          readonly class="w-full rounded border bg-gray-100 px-3 py-2 capitalize"/>
      </div>
    </div>

    <!-- Integrantes -->
    <div class="mb-4 max-h-64 overflow-y-auto rounded border p-3">
      <p class="mb-2 text-sm font-medium">
        Integrantes
      </p>

      <div v-for="(i, index) in integrantes"
        :key="i.id"
        class="border-b py-2 last:border-b-0">
        <p class="mb-1 text-sm font-semibold">
          {{ i.nombre }} {{ i.apellido }}
        </p>

        <!-- Admin con la lista guardada: casillas bloqueadas (el estado solo cambia al aprobar un justificante) -->
        <div class="flex flex-wrap gap-4 text-sm" :class="{ 'opacity-60': soloLectura }">
          <label class="flex items-center gap-1">
            <input
              type="radio"
              :name="`estado-${i.id}`"
              value="asistio"
              :disabled="soloLectura"
              v-model="form.asistencias[index].estado"/>
            Asistió
          </label>

          <label class="flex items-center gap-1">
            <input
              type="radio"
              :name="`estado-${i.id}`"
              value="falto"
              :disabled="soloLectura"
              v-model="form.asistencias[index].estado"/>
            No asistió
          </label>

          <label class="flex items-center gap-1">
            <input
              type="radio"
              :name="`estado-${i.id}`"
              value="justificada"
              :disabled="soloLectura"
              v-model="form.asistencias[index].estado"/>
            Justificada
          </label>
        </div>
      </div>
    </div>

    <!-- Evidencia -->
    <div class="mb-4">
      <label class="mb-1 block text-sm font-medium">
        Evidencia documental (PDF)
      </label>

      <!-- Evidencia ya guardada -->
      <div v-if="evidenciaActual" class="mb-2 rounded border bg-gray-100 px-3 py-2">
        <a :href="`/storage/${evidenciaActual}`" target="_blank" rel="noopener"
          class="font-medium text-blue-700 underline hover:text-blue-900">
          Ver evidencia documental
        </a>
      </div>

      <!-- El admin ya no puede reemplazarla; el super_admin sí -->
      <template v-if="!soloLectura">
        <p v-if="evidenciaActual" class="mb-1 text-xs text-gray-500">
          Adjunta un archivo solo si quieres reemplazar la evidencia actual.
        </p>

        <input
          type="file"
          accept="application/pdf"
          class="w-full rounded border px-3 py-2"
          @change="onFileChange"/>

        <p v-if="errorArchivo" class="mt-1 text-sm text-red-500">
          {{ errorArchivo }}
        </p>

        <p v-if="errores.evidencia" class="mt-1 text-sm text-red-500">
          {{ errores.evidencia }}
        </p>

        <p class="mt-1 text-xs text-gray-500">
          Solo se permiten archivos PDF de hasta 4 MB.
        </p>
      </template>

      <p v-else-if="!evidenciaActual" class="text-sm text-gray-500">
        Sin evidencia documental.
      </p>
    </div>

    <!-- Errores generales del backend -->
    <p v-if="errores.asistencias || errores.fecha" class="mb-4 text-sm text-red-600">
      {{ errores.asistencias || errores.fecha }}
    </p>

    <!-- Botones -->
    <div class="flex justify-end gap-2 border-t pt-4">
      <button type="button"
        class="rounded bg-gray-300 px-4 py-2 transition hover:bg-gray-400 disabled:opacity-50"
        :disabled="procesando"
        @click="emit('close')">
        Cancelar
      </button>

      <button type="button"
        class="rounded bg-gray-700 px-4 py-2 text-white transition hover:bg-gray-800 disabled:opacity-50"
        :disabled="procesando || soloLectura"
        @click="submitForm">
        {{ procesando ? 'Guardando...' : 'Guardar asistencia' }}
      </button>
    </div>
  </DraggableModal>
</template>
