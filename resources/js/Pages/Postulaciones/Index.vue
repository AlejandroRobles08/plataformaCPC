<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, watchEffect, computed } from 'vue'
import { Link, usePage, useForm } from '@inertiajs/vue3'
import { HandRaisedIcon } from '@heroicons/vue/24/solid'
import Swal from 'sweetalert2'
import Form from './Form.vue'

const page = usePage()

const esInvitado = computed(() =>
  page.props.auth?.roles?.includes('invitado')
)

const props = defineProps({
  postulaciones: {
    type: Array,
    default: () => []
  }
})

const showModal = ref(false)
const showExpediente = ref(false)
const selected = ref(null)

const abrirExpediente = (p) => {
  selected.value = p
  showExpediente.value = true
}

const cerrarExpediente = () => {
  selected.value = null
  showExpediente.value = false
}

// Crear usuario invitado
const showInvitado = ref(false)
const invitadoForm = useForm({
  name: '',
  email: ''
})

const cerrarInvitado = () => {
  showInvitado.value = false
  invitadoForm.reset()
  invitadoForm.clearErrors()
}

const crearInvitado = () => {
  invitadoForm.post(route('postulaciones.invitados.store'), {
    preserveScroll: true,
    onSuccess: () => cerrarInvitado()
  })
}

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
  if (estatus === 'aprobada') return 'bg-green-100 text-green-800'
  if (estatus === 'no_aprobada') return 'bg-red-100 text-red-800'
  return 'bg-gray-100 text-gray-800'
}

const estatusLabel = (estatus) => {
  if (estatus === 'pendiente') return 'Pendiente'
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

          <button @click="showModal = true"class="px-4 py-2 text-white rounded-md inline-flex items-center gap-2"
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
                  <th class="px-6 py-3">Acta</th>
                  <th class="px-6 py-3">Estatus</th>
                  <th class="px-6 py-3">Fecha Postulación</th>
                  <th class="px-6 py-3">Fecha Resolución</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-gray-200">
                <tr v-for="p in postulaciones" :key="p.id" class="hover:bg-gray-50 transition">
                  <td class="px-6 py-4 font-medium text-gray-900">
                    {{ p.nombre }} {{ p.apellido_paterno }} {{ p.apellido_materno }}
                  </td>
                  <td class="px-6 py-4">
                    <button @click="abrirExpediente(p)" class="text-yellow-600 hover:underline font-medium">
                      Ver expediente
                    </button>
                  </td>
                  <td class="px-6 py-4">
                    <a v-if="p.acta_resolucion" :href="`/storage/${p.acta_resolucion}`" target="_blank"
                      class="text-blue-600 hover:underline">
                      Ver acta
                    </a>
                    <span v-else class="text-gray-400">
                      —
                    </span>
                  </td>
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

    <!-- MODAL EXPEDIENTE -->
    <div v-if="showExpediente" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white w-full max-w-lg rounded-lg shadow-lg p-6 relative">
        <button @click="cerrarExpediente" class="absolute top-3 right-3 text-gray-500">✕</button>
        <h3 class="text-lg font-semibold mb-4">
          Expediente
        </h3>
        <div v-if="selected?.documentos?.length">
          <ul class="space-y-2">
            <li v-for="doc in selected.documentos" :key="doc.id"
              class="flex justify-between items-center border-b pb-1">
              <span class="text-sm font-medium capitalize">
                {{ doc.tipo.replaceAll('_', ' ') }}
              </span>
              <a :href="`/storage/${doc.archivo}`" target="_blank" class="text-green-600 hover:underline text-sm">
                Ver PDF
              </a>
            </li>
          </ul>
        </div>
        <div v-else class="text-gray-500">
          No hay documentos cargados.
        </div>
      </div>
    </div>
    <!-- MODAL USUARIO INVITADO -->
    <div v-if="showInvitado" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white w-full max-w-md rounded-lg shadow-lg p-6 relative">
        <button @click="cerrarInvitado" class="absolute top-3 right-3 text-gray-500">✕</button>
        <h3 class="text-lg font-semibold mb-4">
          Nuevo usuario invitado
        </h3>
        <form @submit.prevent="crearInvitado" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Nombre</label>
            <input v-model="invitadoForm.name" type="text" required
              class="mt-1 w-full border-gray-300 rounded-md shadow-sm" />
            <p v-if="invitadoForm.errors.name" class="text-red-600 text-sm mt-1">{{ invitadoForm.errors.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Correo</label>
            <input v-model="invitadoForm.email" type="email" required
              class="mt-1 w-full border-gray-300 rounded-md shadow-sm" />
            <p v-if="invitadoForm.errors.email" class="text-red-600 text-sm mt-1">{{ invitadoForm.errors.email }}</p>
          </div>
          <div class="flex justify-end gap-2">
            <button type="button" @click="cerrarInvitado"
              class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
              Cancelar
            </button>
            <button type="submit" :disabled="invitadoForm.processing"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50">
              Crear
            </button>
          </div>
        </form>
      </div>
    </div>

    <Form
  v-if="showModal" @close="showModal = false"/>
  </AuthenticatedLayout>
</template>