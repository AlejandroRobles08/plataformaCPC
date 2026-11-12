  <script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, watchEffect, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { HandRaisedIcon, EyeIcon, HeartIcon, FolderOpenIcon } from '@heroicons/vue/24/solid'
import Swal from 'sweetalert2'
import Form from './Form.vue'
import CrearUsuarioModal from '@/Components/CrearUsuarioModal.vue'

const page = usePage()

const esInvitado = computed(() =>
  page.props.auth?.roles?.includes('invitado')
)
const props = defineProps({
  postulaciones: {
    type: Array,
    default: () => []
  },
  // Postulaciones con documentos cargados que esperan revisión (alerta del Panel de validación)
  documentosPorRevisar: {
    type: Number,
    default: 0
  }
})

// Invitado con documentos ya cargados: puede consultar su expediente y las observaciones
const miPostulacionConDocumentos = computed(() =>
  esInvitado.value
    ? props.postulaciones.find(p => p.documentos?.length) ?? null
    : null
)

// Documentos que no se aprobaron y la persona debe volver a cargar
const documentosNoAprobados = computed(() =>
  ['pendiente', 'pre_aprobada'].includes(miPostulacionConDocumentos.value?.estatus)
    ? miPostulacionConDocumentos.value.documentos.filter(d => d.estatus === 'rechazado').length
    : 0
)

// Invitado que viene de la postulación abierta: ya pre-aprobado, falta subir documentos
const postulacionPorDocumentar = computed(() =>
  esInvitado.value
    ? props.postulaciones.find(p => p.estatus === 'pre_aprobada' && !p.documentos?.length) ?? null
    : null
)

// El super_admin y el invitado sin postulación pueden postularse;
// el invitado pre-aprobado solo carga sus documentos
const puedePostularse = computed(() =>
  page.props.auth?.roles?.includes('super_admin')
  || (esInvitado.value && (!props.postulaciones.length || !!postulacionPorDocumentar.value))
)

// Invitado cuya postulación no fue aprobada: se le muestra un mensaje amable en lugar de solo el estatus
const postulacionNoAprobada = computed(() =>
  esInvitado.value
    ? props.postulaciones.find(p => p.estatus === 'no_aprobada') ?? null
    : null
)

const showModal = ref(false)

// Crear usuario invitado
const showInvitado = ref(false)

watchEffect(() => {
  if (page.props.flash?.success) {
    Swal.fire({
      icon: 'success',
      title: 'Correcto',
      text: page.props.flash.success
    })
  }
  if (page.props.flash?.error) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: page.props.flash.error
    })
  }
})

//funciones para mostrar estatus con colores y formatear fechas
const estatusClass = (estatus) => {
  if (estatus === 'pendiente') return 'bg-yellow-100 text-yellow-800'
  if (estatus === 'pre_aprobada') return 'bg-blue-100 text-blue-800'
  if (estatus === 'aprobada') return 'bg-green-100 text-green-800'
  if (estatus === 'no_aprobada') return 'bg-red-100 text-red-800'
  return 'bg-gray-100 text-gray-800'
}

const estatusLabel = (estatus) => {
  if (estatus === 'pendiente') return 'Pendiente'
  if (estatus === 'pre_aprobada') return 'Pre-aprobada'
  if (estatus === 'aprobada') return 'Aprobada'
  if (estatus === 'no_aprobada') return 'No Aprobada'
  return 'Desconocido'
}

const formatearFecha = (fecha) => {
  if (!fecha) return '-'
  const f = fecha.split('T')[0]
  const [year, month, day] = f.split('-')
  return `${day}/${month}/${year}`
}

//EXPORTAR EXCEL
function exportarPostulacionesExcel() {
  const tabla = document.querySelector('#tabla-postulaciones-excel')
  if (!tabla) return
  const blob = new Blob(['\ufeff' + tabla.outerHTML], {
    type: 'application/vnd.ms-excel'
  })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = 'postulaciones.xls'
  link.click()
}
</script>


<template>

  <AuthenticatedLayout>
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-2xl font-bold">
        Sección de Postulaciones
      </h1>
    </div>

    <div v-if="!esInvitado" class="mb-4 flex justify-between">
      <Link :href="route('postulaciones.validacion')"
        class="relative px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
        Panel de validación
        <!-- Aviso: hay postulaciones con documentos cargados por revisar -->
        <span v-if="documentosPorRevisar"
          :title="`${documentosPorRevisar} ${documentosPorRevisar === 1 ? 'postulación tiene' : 'postulaciones tienen'} documentos por revisar`"
          class="absolute -top-2 -right-2 min-w-6 h-6 px-1 flex items-center justify-center
                 rounded-full bg-red-600 text-white text-xs font-bold ring-2 ring-white">
          <span class="absolute inset-0 animate-ping rounded-full bg-red-600 opacity-60"></span>
          <span class="relative">{{ documentosPorRevisar }}</span>
        </span>
      </Link>

      <button @click="showInvitado = true"
        class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-800">
        Crear usuario invitado
      </button>
    </div>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Mensaje para el invitado cuya postulación no fue aprobada -->
        <section v-if="postulacionNoAprobada" role="status"
          class="mb-6 overflow-hidden rounded-lg border border-puebla-guinda/20 bg-white shadow-sm">
          <div class="h-1 bg-puebla-guinda"></div>
          <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-start">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-puebla-guinda/10">
              <HeartIcon class="h-6 w-6 text-puebla-guinda" aria-hidden="true" />
            </div>
            <div class="space-y-3 text-sm text-gray-600">
              <h2 class="text-lg font-semibold text-gray-800">
                Gracias por postularse, {{ postulacionNoAprobada.nombre }}
              </h2>
              <p>
                Después de revisar cuidadosamente todas las postulaciones, le informamos que en esta ocasión
                su postulación <span class="font-semibold text-gray-800">no fue aprobada</span>.
              </p>
              <p>
                Este resultado no demerita su trayectoria ni su compromiso con la comunidad: los lugares en cada
                consejo son limitados y la selección responde a los perfiles requeridos en esta convocatoria.
              </p>
              <div class="rounded-md bg-puebla-arena-claro px-4 py-3 text-gray-700">
                <p class="font-semibold text-gray-800">Le invitamos a seguir participando</p>
                <p class="mt-1">
                  Las convocatorias para integrar los Consejos de Participación Ciudadana se abren de manera periódica.
                  Nos dará mucho gusto recibir nuevamente su postulación en una próxima ocasión.
                </p>
              </div>
            </div>
          </div>
        </section>

        <!-- Aviso para el invitado con documentos no aprobados -->
        <div v-if="documentosNoAprobados" role="alert"
          class="mb-6 flex flex-col gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 sm:flex-row sm:items-center sm:justify-between">
          <p>
            <span class="font-semibold">
              {{ documentosNoAprobados === 1 ? 'Un documento no fue aprobado.' : `${documentosNoAprobados} documentos no fueron aprobados.` }}
            </span>
            Revise las observaciones y cargue una nueva versión para continuar con su postulación.
          </p>
          <Link :href="route('postulaciones.documentos')"
            class="shrink-0 rounded-md bg-red-600 px-4 py-2 text-center font-semibold text-white hover:bg-red-700">
            Ver observaciones
          </Link>
        </div>

        <div class="mb-4 flex justify-end space-x-2">
          <button v-if="!esInvitado" @click="exportarPostulacionesExcel"
            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
            Exportar Excel
          </button>

          <Link v-if="miPostulacionConDocumentos" :href="route('postulaciones.documentos')"
            class="relative px-4 py-2 rounded-md inline-flex items-center gap-2 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
            <FolderOpenIcon class="w-5 h-5" />
            <span>Mis documentos</span>
            <!-- Aviso: documentos no aprobados con observaciones por atender -->
            <span v-if="documentosNoAprobados"
              :title="`${documentosNoAprobados} ${documentosNoAprobados === 1 ? 'documento no fue aprobado' : 'documentos no fueron aprobados'}`"
              class="absolute -top-2 -right-2 min-w-6 h-6 px-1 flex items-center justify-center
                     rounded-full bg-red-600 text-white text-xs font-bold ring-2 ring-white">
              <span class="absolute inset-0 animate-ping rounded-full bg-red-600 opacity-60"></span>
              <span class="relative">{{ documentosNoAprobados }}</span>
            </span>
          </Link>

          <button v-if="puedePostularse" @click="showModal = true" class="px-4 py-2 text-white rounded-md inline-flex items-center gap-2"
            style="background-color:#1E520B;">
              <span>{{ postulacionPorDocumentar ? 'Cargar documentos' : 'Postularme' }}</span>
                <HandRaisedIcon class="w-5 h-5" />
          </button>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <div v-if="!postulaciones.length" class="text-gray-500">
            No hay postulaciones registradas.
          </div>

          <div v-else class="overflow-x-auto">
            <table class="min-w-full text-sm text-left border border-gray-200">
              <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
                <tr>
                  <th class="px-6 py-3">Nombre</th>
                  <th class="px-6 py-3">Expediente</th>
                  <th class="px-6 py-3">Estatus</th>
                  <th class="px-6 py-3">Fecha Postulación</th>
                  <th class="px-6 py-3">Fecha Resolución</th>
                  <th v-if="!esInvitado" class="px-6 py-3 text-center">Detalle</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-gray-200">
                <tr v-for="p in postulaciones" :key="p.id" class="hover:bg-gray-50 transition">
                  <td class="px-6 py-4 font-medium text-gray-900">
                    {{ p.nombre }} {{ p.apellido_paterno }} {{ p.apellido_materno }}
                  </td>
                  <td class="px-6 py-4 text-gray-400">s/d</td>
                  <td class="px-6 py-4">
                    <span :class="[
                      'px-3 py-1 text-xs rounded-full font-semibold',
                      estatusClass(p.estatus)
                    ]">
                      {{ estatusLabel(p.estatus) }}
                    </span>
                  </td>
                  <td class="px-6 py-4">
                    {{ formatearFecha(p.fecha_postulacion) }}
                  </td>
                  <td class="px-6 py-4">
                    {{ formatearFecha(p.fecha_validacion) }}
                  </td>
                  <td v-if="!esInvitado" class="px-6 py-4 text-center">
                    <Link :href="route('postulaciones.show', p.id)"
                      class="inline-flex items-center gap-1 px-3 py-1 rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200 text-xs font-medium">
                      <EyeIcon class="w-4 h-4" aria-hidden="true" />
                      Ver detalle
                    </Link>
                  </td>
                </tr>
              </tbody>
            </table>

          </div>
        </div>
      </div>
    </div>

    <!-- TABLA OCULTA PARA EXPORTAR -->
    <table id="tabla-postulaciones-excel" style="display:none">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Apellido Paterno</th>
          <th>Apellido Materno</th>
          <th>Estatus</th>
          <th>Fecha Postulación</th>
          <th>Fecha Resolución</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="p in postulaciones" :key="p.id">
          <td>{{ p.nombre }}</td>
          <td>{{ p.apellido_paterno }}</td>
          <td>{{ p.apellido_materno }}</td>
          <td>{{ estatusLabel(p.estatus) }}</td>
          <td>{{ formatearFecha(p.fecha_postulacion) }}</td>
          <td>{{ formatearFecha(p.fecha_validacion) }}</td>
        </tr>
      </tbody>
    </table>

    <!-- MODAL USUARIO INVITADO -->
    <CrearUsuarioModal v-if="showInvitado" rol="invitado" ruta="postulaciones.invitados.store"
      titulo="Nuevo usuario invitado" @close="showInvitado = false" @created="showInvitado = false" />
    <Form v-if="puedePostularse && showModal" :postulacion="postulacionPorDocumentar" @close="showModal = false"/>
  </AuthenticatedLayout>
</template>