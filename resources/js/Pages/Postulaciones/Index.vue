<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, watchEffect, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { HandRaisedIcon, EyeIcon } from '@heroicons/vue/24/solid'
import Swal from 'sweetalert2'
import Form from './Form.vue'
import CrearUsuarioModal from '@/Components/CrearUsuarioModal.vue'

const page = usePage()

const esInvitado = computed(() =>
  page.props.auth?.roles?.includes('invitado')
)
// El invitado y el super_admin pueden postularse
const puedePostularse = computed(() =>
  esInvitado.value || page.props.auth?.roles?.includes('super_admin')
)

const props = defineProps({
  postulaciones: {
    type: Array,
    default: () => []
  }
})

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
        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
        Panel de validación
      </Link>

      <button @click="showInvitado = true"
        class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-800">
        Crear usuario invitado
      </button>
    </div>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="mb-4 flex justify-end space-x-2">
          <button v-if="!esInvitado" @click="exportarPostulacionesExcel"
            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
            Exportar Excel
          </button>

          <button v-if="puedePostularse" @click="showModal = true" class="px-4 py-2 text-white rounded-md inline-flex items-center gap-2"
            style="background-color:#1E520B;">
              <span>Postularme</span>
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
    <Form v-if="puedePostularse && showModal" @close="showModal = false"/>
  </AuthenticatedLayout>
</template>