<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import DraggableModal from '@/Components/DraggableModal.vue'

const props = defineProps({
  show: Boolean,
  justificantes: {
    type: Array,
    default: () => []
  }
})
const emit = defineEmits(['close'])
const seleccionado = ref(null)

// Abre el detalle del justificante.
function abrir(justificante) {
  seleccionado.value = justificante
}

// Cierra el modal y limpia la selección.
function cerrar() {
  seleccionado.value = null
  emit('close')
}

// Regresa a la lista de justificantes.
function volver() {
  seleccionado.value = null
}

// Aprueba el justificante seleccionado.
function aprobar() {
  if (!seleccionado.value) return

  Swal.fire({
    title: '¿Aprobar justificante?',
    text: 'La asistencia será marcada como justificada.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Aprobar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#15803d',
  }).then(result => {
    if (!result.isConfirmed) return

    router.patch(
      route('justificantes.aprobar', seleccionado.value.id),
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          cerrar()
          Swal.fire({
            icon: 'success',
            title: 'Justificante aprobado',
            text: 'La asistencia fue marcada como justificada.'
          })
        },

        onError: errors => {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: errors.justificante ?? 'No fue posible aprobar el justificante.'
          })
        }
      }
    )
  })
}

// Rechaza el justificante seleccionado.
function rechazar() {
  if (!seleccionado.value) return
  Swal.fire({
    title: '¿Rechazar justificante?',
    text: 'La asistencia permanecerá como falta.',
    icon: 'warning',
    input: 'textarea',
    inputLabel: 'Motivo por el cual no se aprueba',
    inputPlaceholder: 'Escribe el motivo del rechazo...',
    inputAttributes: { maxlength: 1000 },
    inputValidator: value => !value?.trim() && 'Indica el motivo del rechazo.',
    showCancelButton: true,
    confirmButtonText: 'Rechazar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#b91c1c',
  }).then(result => {
    if (!result.isConfirmed) return
    router.patch(
      route('justificantes.rechazar', seleccionado.value.id),
      { comentario_justificante: result.value.trim() },
      {
        preserveScroll: true,
        onSuccess: () => {
          cerrar()
          Swal.fire({
            icon: 'success',
            title: 'Justificante rechazado',
            text: 'La asistencia permanece marcada como falta.'
          })
        },
        onError: errors => {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: errors.comentario_justificante ?? errors.justificante ?? 'No fue posible rechazar el justificante.'
          })
        }
      }
    )
  })
}
</script>

<template>
  <DraggableModal v-if="show"
    :title="seleccionado ? 'Revisar justificante' : 'Justificantes'"
    @close="cerrar">
    <!-- Lista de justificantes -->
    <div v-if="!seleccionado" class="space-y-3">
      <div v-for="j in justificantes"
        :key="j.id"
        class="flex items-center justify-between gap-4 rounded border p-4">
        <div>
          <p class="font-semibold">
            {{ j.integrante?.nombre }}
            {{ j.integrante?.apellido }}
          </p>

          <p class="text-sm text-gray-500">
            {{ j.fecha }} · {{ j.tipo_sesion }}
          </p>

          <p class="text-sm font-medium capitalize">
            Estado: {{ j.estado_justificante }}
          </p>
        </div>

        <button type="button" class="rounded bg-gray-700 px-4 py-2 text-white hover:bg-gray-900"
          @click="abrir(j)">
          Revisar
        </button>
      </div>

      <p v-if="!justificantes.length" class="py-6 text-center text-gray-500">
        No hay justificantes subidos aún.
      </p>
    </div>

    <!-- Detalle del justificante -->
    <div v-else>
      <button type="button" class="mb-4 text-gray-600 hover:underline"
        @click="volver">
        ← Volver
      </button>

      <h3 class="mb-1 text-lg font-bold">
        {{ seleccionado.integrante?.nombre }} 
        {{ seleccionado.integrante?.apellido }}
      </h3>

      <p class="mb-4 text-sm text-gray-500">
        Sesión realizada el {{ seleccionado.fecha }}
      </p>

      <p class="mb-4 text-sm text-gray-500">
        Tipo de sesión: {{ seleccionado.tipo_sesion }}
      </p>

      <!-- Visualización del PDF -->
      <iframe :src="route('justificantes.show', seleccionado.id)"
        class="mb-4 h-[450px] w-full rounded border">
      </iframe>

      <!-- Acciones de validación -->
      <div v-if="seleccionado.estado_justificante === 'pendiente'"
        class="flex justify-end gap-3">
        <button type="button" class="rounded bg-red-700 px-4 py-2 text-white hover:bg-red-900"
          @click="rechazar">
          Rechazar
        </button>

        <button type="button" class="rounded bg-green-700 px-4 py-2 text-white hover:bg-green-900"
          @click="aprobar">
          Aprobar
        </button>
      </div>

      <!-- Estado cuando ya fue validado -->
      <div v-else class="rounded bg-gray-100 p-3 text-center text-sm text-gray-600">
        Este justificante ya fue procesado.
      </div>
    </div>
  </DraggableModal>
</template>