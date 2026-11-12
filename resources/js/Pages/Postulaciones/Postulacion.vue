<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { computed, ref, watchEffect } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import { CheckBadgeIcon, XMarkIcon, EyeIcon, DocumentTextIcon, CheckCircleIcon, XCircleIcon, ClockIcon,
  HandThumbUpIcon, HandThumbDownIcon } from '@heroicons/vue/24/solid'
import Aprobar from './Aprobar.vue'

const page = usePage()
const props = defineProps({
  postulaciones: { type: Array, default: () => [] },
  consejos: { type: Array, default: () => [] },
  nombresDocumentos: { type: Object, default: () => ({}) }
})

// Se guarda solo el id: al aprobar / no aprobar un documento llegan props nuevos
// y el modal debe mostrar el estado actualizado
const idSeleccionado = ref(null)
const postulacionSeleccionada = computed(() =>
  props.postulaciones.find(p => p.id === idSeleccionado.value) ?? null
)
const mostrarExpediente = computed(() => !!postulacionSeleccionada.value)

const abrirExpediente = postulacion => {
  idSeleccionado.value = postulacion.id
}

const cerrarExpediente = () => {
  idSeleccionado.value = null
}

// Estado de los documentos (mismo flujo que el archivo digital)
const ESTILOS_DOC = {
  aprobado: { texto: 'Aprobado', icono: CheckCircleIcon, insignia: 'bg-green-100 text-green-800 ring-green-200', iconoColor: 'text-green-600' },
  pendiente: { texto: 'Por revisar', icono: ClockIcon, insignia: 'bg-amber-100 text-amber-800 ring-amber-200', iconoColor: 'text-amber-600' },
  rechazado: { texto: 'No aprobado', icono: XCircleIcon, insignia: 'bg-red-100 text-red-800 ring-red-200', iconoColor: 'text-red-600' },
}
const estiloDoc = doc => ESTILOS_DOC[doc.estatus] ?? ESTILOS_DOC.pendiente
const nombreDocumento = doc => props.nombresDocumentos[doc.tipo] ?? doc.tipo.replaceAll('_', ' ')

const contar = (postulacion, estatus) => (postulacion.documentos ?? []).filter(d => d.estatus === estatus).length
const porRevisar = postulacion => contar(postulacion, 'pendiente')
// Solo se aprueba la postulación con todos sus documentos aprobados
const expedienteAprobado = postulacion =>
  Object.keys(props.nombresDocumentos).every(tipo =>
    postulacion.documentos?.some(d => d.tipo === tipo && d.estatus === 'aprobado'))

const aprobarDocumento = async doc => {
  const { isConfirmed } = await Swal.fire({
    title: '¿Aprobar documento?', text: `${nombreDocumento(doc)} será marcado como aprobado.`,
    icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, aprobar', cancelButtonText: 'Cancelar',
    confirmButtonColor: '#15803d'
  })
  if (isConfirmed) {
    router.patch(route('postulaciones.documentos.aprobar', doc.id), {}, { preserveScroll: true, preserveState: true })
  }
}

const rechazarDocumento = async doc => {
  const { isConfirmed, value } = await Swal.fire({
    title: 'No se aprobará el documento',
    text: 'Indique las observaciones para la persona postulante.',
    input: 'textarea', inputLabel: 'Observación',
    inputPlaceholder: 'Describa las razones por las que no se aprueba el documento.',
    inputAttributes: { 'aria-label': 'Observación del documento', maxlength: 2000 },
    showCancelButton: true, confirmButtonText: 'No aprobar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc2626',
    inputValidator: v => !v?.trim() && 'Debes escribir una observación.'
  })
  if (isConfirmed) {
    router.patch(route('postulaciones.documentos.rechazar', doc.id), { observacion: value },
      { preserveScroll: true, preserveState: true })
  }
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
  if (flash?.warning) {
    Swal.fire({
      icon: 'warning',
      title: 'Atención',
      text: flash.warning
    })
  }
})

// Aprobar postulación (modal con fecha, acta y consejo)
const postulacionAprobar = ref(null)

const aprobar = postulacion => {
  if (!expedienteAprobado(postulacion)) {
    Swal.fire({
      icon: 'info',
      title: 'Expediente sin aprobar',
      text: 'Antes de aprobar la postulación, revise y apruebe todos los documentos del expediente.',
    })
    return
  }
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
                    class="relative text-blue-600 hover:text-blue-800"
                    :title="porRevisar(p) ? `${porRevisar(p)} documento(s) por revisar` : 'Revisar expediente'">
                    <EyeIcon class="w-6 h-6" />
                    <!-- Aviso: la persona subió documentos que esperan revisión -->
                    <span v-if="porRevisar(p)"
                      class="absolute -top-2 -right-3 min-w-5 h-5 px-1 flex items-center justify-center
                             rounded-full bg-red-600 text-white text-xs font-bold ring-2 ring-white">
                      <span class="absolute inset-0 animate-ping rounded-full bg-red-600 opacity-60"></span>
                      <span class="relative">{{ porRevisar(p) }}</span>
                    </span>
                  </button>
                  <p class="mt-1 text-xs text-gray-500">
                    {{ contar(p, 'aprobado') }} de {{ Object.keys(nombresDocumentos).length }} aprobados
                  </p>
                </td>

                <td class="px-4 py-3 text-center space-x-3">
                  <button @click="aprobar(p)"
                    :class="expedienteAprobado(p) ? 'text-green-600 hover:text-green-800' : 'text-gray-300 cursor-not-allowed'"
                    :title="expedienteAprobado(p) ? 'Aprobar postulación' : 'Apruebe todos los documentos para aprobar la postulación'">
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
                class="flex flex-col gap-3 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                  <div class="flex items-center gap-3">
                    <component :is="estiloDoc(documento).icono" class="w-6 h-6 shrink-0" :class="estiloDoc(documento).iconoColor" />
                    <span class="font-medium text-gray-800">{{ nombreDocumento(documento) }}</span>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset" :class="estiloDoc(documento).insignia">
                      {{ estiloDoc(documento).texto }}
                    </span>
                  </div>

                  <div class="flex flex-wrap gap-2 text-sm font-medium">
                    <button @click="verDocumento(documento.archivo)"
                      class="inline-flex items-center gap-1 px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                      <DocumentTextIcon class="w-4 h-4" /> Ver PDF
                    </button>
                    <template v-if="documento.estatus === 'pendiente'">
                      <button type="button" @click="aprobarDocumento(documento)"
                        class="inline-flex items-center gap-1 px-3 py-2 rounded bg-green-600 text-white hover:bg-green-700">
                        <HandThumbUpIcon class="w-4 h-4" /> Aprobar
                      </button>
                      <button type="button" @click="rechazarDocumento(documento)"
                        class="inline-flex items-center gap-1 px-3 py-2 rounded border border-red-300 text-red-700 hover:bg-red-50">
                        <HandThumbDownIcon class="w-4 h-4" /> No aprobar
                      </button>
                    </template>
                  </div>
                </div>

                <div v-if="documento.estatus === 'rechazado'" class="space-y-2">
                  <div v-if="documento.observacion" class="rounded-lg border border-red-200 bg-red-50 p-3">
                    <p class="mb-1 text-xs font-semibold uppercase text-red-700">Observación</p>
                    <p class="text-sm text-red-700 whitespace-pre-line break-words">{{ documento.observacion }}</p>
                  </div>
                  <p class="flex items-center gap-1.5 text-xs text-gray-600">
                    <ClockIcon class="h-4 w-4 shrink-0 text-gray-400" />
                    En espera de que la persona suba una nueva versión del documento.
                  </p>
                </div>
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