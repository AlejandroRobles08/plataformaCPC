<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, watchEffect } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import { CheckBadgeIcon, XMarkIcon, EyeIcon, DocumentTextIcon } from '@heroicons/vue/24/solid'
import Aprobar from './Aprobar.vue'

const page = usePage()
const props = defineProps({
  postulaciones: { type: Array, default: () => [] },
  consejos: { type: Array, default: () => [] }
})
const postulacionSeleccionada = ref(null)
const mostrarExpediente = ref(false)

const abrirExpediente = postulacion => {
  postulacionSeleccionada.value = postulacion
  mostrarExpediente.value = true
}

const cerrarExpediente = () => {
  postulacionSeleccionada.value = null
  mostrarExpediente.value = false
}

const verDocumento = archivo => {
  window.open(`/storage/${archivo}`, '_blank')
}

// Mensajes del sistema
watchEffect(() => {
  const flash = page.props.flash
  if (flash?.success) {
    Swal.fire({
      icon: 'success',
      title: 'Correcto',
      text: flash.success
    })
  }
  if (flash?.error) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: flash.error
    })
  }
})

// Aprobar postulación (modal con fecha, acta y consejo)
const postulacionAprobar = ref(null)

const aprobar = postulacion => {
  postulacionAprobar.value = postulacion
}

const cerrarAprobar = () => {
  postulacionAprobar.value = null
}

// Rechazar postulación
const rechazar = id => {
  Swal.fire({
    title: 'Rechazar postulación',
    html: `
      <label style="display:block;text-align:left;margin:10px 0 5px">Fecha de validación</label>
      <input type="date" id="fecha" class="swal2-input">
      <label style="display:block;text-align:left;margin:10px 0 5px">Acta de resolución (PDF)</label>
      <input type="file" id="acta" class="swal2-file" accept="application/pdf">
    `,
    showCancelButton: true,
    confirmButtonText: 'No aprobar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    preConfirm: () => {
      const fecha = document.getElementById('fecha').value
      const archivo = document.getElementById('acta').files[0]

      if (!fecha || !archivo) {
        Swal.showValidationMessage('Debe capturar la fecha y subir el acta de resolución.')
        return false
      }
      return { fecha, archivo }
    }
  }).then(result => {
    if (!result.isConfirmed) return
    const formData = new FormData()
    formData.append('fecha_validacion', result.value.fecha)
    formData.append('acta_resolucion', result.value.archivo)
    router.post(route('postulaciones.rechazar', id), formData)
  })
}
</script>

<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold">Panel de Validación</h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div v-if="!postulaciones.length" class="text-gray-500">
          No hay postulaciones pendientes.
        </div>
        <div v-else class="bg-white shadow sm:rounded-lg p-6 overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">

            <thead>
              <tr>
                <th class="px-4 py-3 text-left">Nombre</th>
                <th class="px-4 py-3 text-center">Expediente</th>
                <th class="px-4 py-3 text-center">Resolución</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-gray-200">
              <tr v-for="p in postulaciones" :key="p.id">

                <td class="px-4 py-3">
                  {{ p.nombre }} {{ p.apellido_paterno }} {{ p.apellido_materno }}
                </td>

                <td class="px-4 py-3 text-center">
                  <button @click="abrirExpediente(p)"
                    class="text-blue-600 hover:text-blue-800"
                    title="Revisar expediente">
                    <EyeIcon class="w-6 h-6" />
                  </button>
                </td>

                <td class="px-4 py-3 text-center space-x-3">
                  <button @click="aprobar(p)"
                    class="text-green-600 hover:text-green-800"
                    title="Aprobar postulación">
                    <CheckBadgeIcon class="w-6 h-6" />
                  </button>

                  <button @click="rechazar(p.id)"
                    class="text-red-600 hover:text-red-800"
                    title="No aprobar postulación">
                    <XMarkIcon class="w-6 h-6" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal para aprobar -->
    <Aprobar v-if="postulacionAprobar" :postulacion="postulacionAprobar" :consejos="consejos"
      @close="cerrarAprobar" />

    <!-- Modal del expediente -->
    <div v-if="mostrarExpediente && postulacionSeleccionada"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4">
      <div class="bg-white w-full max-w-4xl rounded-lg shadow-xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b p-5">
          <div>
            <h3 class="text-xl font-semibold text-gray-800">
              Expediente de postulación
            </h3>

            <p class="text-sm text-gray-500">
              {{ postulacionSeleccionada.nombre }}
              {{ postulacionSeleccionada.apellido_paterno }}
                {{ postulacionSeleccionada.apellido_materno }}
            </p>
          </div>

          <button @click="cerrarExpediente" class="text-gray-500 hover:text-gray-800">
            <XMarkIcon class="w-7 h-7" />
          </button>
        </div>

        <div class="p-6 space-y-6">
          <!-- Datos del postulante -->
          <div>
            <h4 class="font-semibold text-gray-700 mb-3">
              Datos de la postulación
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">

              <div>
                <span class="font-semibold">Nombre:</span>
                {{ postulacionSeleccionada.nombre }}
                {{ postulacionSeleccionada.apellido_paterno }}
                {{ postulacionSeleccionada.apellido_materno }}
              </div>

              <div>
                <span class="font-semibold">Correo:</span>
                {{ postulacionSeleccionada.correo }}
              </div>
            </div>
          </div>

          <!-- Documentos -->
          <div>
            <h4 class="font-semibold text-gray-700 mb-3">
              Documentos del expediente
            </h4>

            <div v-if="!postulacionSeleccionada.documentos?.length"
              class="text-gray-500 text-sm">
              No se encontraron documentos.
            </div>

            <div v-else class="border rounded-lg divide-y">
              <div v-for="documento in postulacionSeleccionada.documentos"
                :key="documento.id"
                class="flex items-center justify-between gap-4 p-4">
                <div class="flex items-center gap-3">
                  <DocumentTextIcon class="w-6 h-6 text-red-600 shrink-0" />
                  <span class="capitalize">
                    {{ documento.tipo.replaceAll('_', ' ') }}
                  </span>
                </div>

                <button @click="verDocumento(documento.archivo)"
                  class="px-3 py-2 text-sm bg-blue-600 text-white rounded hover:bg-blue-700">
                  Ver PDF
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="border-t p-5 flex justify-end">
          <button @click="cerrarExpediente" class="px-4 py-2 bg-gray-600 text-white 
          rounded hover:bg-gray-700">
            Cerrar
          </button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>