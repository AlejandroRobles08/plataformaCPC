<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useForm, Link, router } from '@inertiajs/vue3'
import Pagination from '@/Components/Pagination.vue'
import { CheckCircleIcon, XCircleIcon, BookmarkIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/solid'
import { computed, ref, watch } from 'vue'
import Swal from 'sweetalert2';

const props = defineProps({
  consejo: Object,
  convocatorias: Object,
  filtros: Object,
})

const form = useForm({
  tipo_sesion: '',
  fecha: '',
  convocatoria_documento: null,
  documento: null,
  lista_asistencia: null,
  evidencia: null,
  estado_convocatoria: false,
  estado_sesion: false,
})

watch(() => form.estado_convocatoria, (val) => {
  if (!val) form.estado_sesion = false
})

function handleConvocatoria(e) {
  form.convocatoria_documento = e.target.files[0]
}

function handleDocumento(e) {
  form.documento = e.target.files[0]
}

function handleLista(e) {
  form.lista_asistencia = e.target.files[0]
}

function handleEvidencia(e) {
  form.evidencia = e.target.files[0]
}

function submit() {
  form.post(route('convocatorias.store', { consejo: props.consejo.id }), {
    forceFormData: true,
    onSuccess: () => {
      Swal.fire({
        icon: 'success',
        title: 'Convocatoria registrada',
        text: 'La convocatoria se guardó correctamente.',
        confirmButtonColor: '#16a34a'
      })

      form.reset()
    },
    onError: (errors) => {
      console.log(errors)

      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'No se pudo registrar la convocatoria.'
      })
    }
  })
}

// Buscador por fecha (filtra en el servidor y conserva la paginación)
const fechaDesde = ref(props.filtros?.fecha_desde ?? '')
const fechaHasta = ref(props.filtros?.fecha_hasta ?? '')

function buscar() {
  router.get(
    route('convocatorias.index', { consejo: props.consejo.id }),
    {
      fecha_desde: fechaDesde.value || undefined,
      fecha_hasta: fechaHasta.value || undefined,
    },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}

function limpiarBusqueda() {
  fechaDesde.value = ''
  fechaHasta.value = ''
  buscar()
}

const hayFiltros = computed(() => !!(props.filtros?.fecha_desde || props.filtros?.fecha_hasta))

function urlDocumento(item, campo) {
  return route('convocatorias.documento', { convocatoria: item.id, campo })
}

const mostrarConvocatoria = computed(() => form.estado_convocatoria)
const mostrarSesionDocs = computed(() => form.estado_convocatoria && form.estado_sesion)
</script>

<template>
  <AuthenticatedLayout>

    <div class="mt-2 mb-4">
      <Link :href="route('consejos.convocatorias', consejo.id)" class="text-gray-600 hover:underline">
        ← Volver a Consejos de Participación Ciudadana
      </Link>
    </div>

    <div class="p-6 bg-white rounded-lg shadow-md">
      <h1 class="text-2xl font-bold mb-4 text-gray-800">
        Consejo de Participación Ciudadana de {{ props.consejo.nombre }}
      </h1>

      <form @submit.prevent="submit" class="space-y-4 mb-8">

        <!-- ESTADOS -->
        <div class="flex items-center gap-6">
          <label class="flex items-center space-x-2">
            <input type="checkbox" v-model="form.estado_convocatoria"
              class="rounded border-gray-300 text-green-600 focus:ring-green-500" />
            <span class="text-gray-700">Convocatoria realizada</span>
          </label>

          <label class="flex items-center space-x-2">
            <input type="checkbox" v-model="form.estado_sesion" :disabled="!form.estado_convocatoria"
              class="rounded border-gray-300 text-green-600 focus:ring-green-500" />
            <span class="text-gray-700">Sesión realizada</span>
          </label>
        </div>

        <!-- Tipo de sesión -->
        <div>
          <label class="block font-semibold mb-1 text-gray-700">Tipo de sesión</label>
          <select v-model="form.tipo_sesion"
            class="w-full border-gray-300 focus:border-green-500 focus:ring-green-500 rounded-lg p-2" required>
            <option disabled value="">Selecciona el tipo de sesión</option>
            <option value="ordinaria">Ordinaria</option>
            <option value="extraordinaria">Extraordinaria</option>
            <option value="solemne">Solemne</option>
          </select>
        </div>

        <!-- Fecha -->
        <div>
          <label class="block font-semibold mb-1 text-gray-700">Fecha</label>
          <input v-model="form.fecha" type="date"
            class="w-full border-gray-300 focus:border-green-500 focus:ring-green-500 rounded-lg p-2" required />
        </div>

        <!-- DOCUMENTOS -->
        <div v-if="mostrarConvocatoria">
          <h2 class="text-lg font-semibold mb-2">Lista de documentos</h2>

          <!-- Convocatoria -->
          <label class="block font-semibold mb-1 text-gray-700">Acta de convocatoria</label>
          <div
            class="relative flex items-center border border-gray-300 rounded-lg p-2 bg-gray-50 hover:bg-gray-100 cursor-pointer transition mb-4">
            <ArrowUpTrayIcon class="w-5 h-5 text-green-600 mr-2" />
            <label class="flex-1 cursor-pointer text-gray-700">
              <span v-if="!form.convocatoria_documento">Seleccionar archivo...</span>
              <span v-else>{{ form.convocatoria_documento.name }}</span>
              <input type="file" @change="handleConvocatoria" class="hidden" accept=".pdf" />
            </label>
          </div>

          <!-- Documentos de sesión -->
          <div v-if="mostrarSesionDocs">

            <!-- Acta sesión -->
            <label class="block font-semibold mb-1 text-gray-700">Acta de sesión</label>
            <div
              class="relative flex items-center border border-gray-300 rounded-lg p-2 bg-gray-50 hover:bg-gray-100 cursor-pointer transition mb-4">
              <ArrowUpTrayIcon class="w-5 h-5 text-green-600 mr-2" />
              <label class="flex-1 cursor-pointer text-gray-700">
                <span v-if="!form.documento">Seleccionar archivo...</span>
                <span v-else>{{ form.documento.name }}</span>
                <input type="file" @change="handleDocumento" class="hidden" accept=".pdf" />
              </label>
            </div>

            <!-- Lista -->
            <label class="block font-semibold mb-1 text-gray-700">Lista de asistencia</label>
            <div
              class="relative flex items-center border border-gray-300 rounded-lg p-2 bg-gray-50 hover:bg-gray-100 cursor-pointer transition mb-4">
              <ArrowUpTrayIcon class="w-5 h-5 text-green-600 mr-2" />
              <label class="flex-1 cursor-pointer text-gray-700">
                <span v-if="!form.lista_asistencia">Seleccionar archivo...</span>
                <span v-else>{{ form.lista_asistencia.name }}</span>
                <input type="file" @change="handleLista" class="hidden" accept=".pdf" />
              </label>
            </div>

            <!-- Evidencia -->
            <label class="block font-semibold mb-1 text-gray-700">Evidencia fotográfica</label>
            <div
              class="relative flex items-center border border-gray-300 rounded-lg p-2 bg-gray-50 hover:bg-gray-100 cursor-pointer transition">
              <ArrowUpTrayIcon class="w-5 h-5 text-green-600 mr-2" />
              <label class="flex-1 cursor-pointer text-gray-700">
                <span v-if="!form.evidencia">Seleccionar archivo...</span>
                <span v-else>{{ form.evidencia.name }}</span>
                <input type="file" @change="handleEvidencia" class="hidden" accept=".pdf" />
              </label>
            </div>

          </div>
        </div>

        <!-- Guardar -->
        <div class="flex justify-end">
          <button type="submit"
            class="flex items-center gap-2 px-5 py-2.5 bg-green-600 text-white font-semibold rounded-xl shadow-md hover:bg-green-700 hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-150">
            <BookmarkIcon class="w-5 h-5" />
            Guardar
          </button>
        </div>

      </form>
    </div>

    <!-- TABLA -->
    <div class="p-6 bg-white rounded-lg shadow-md">

      <!-- BUSCADOR POR FECHA -->
      <form @submit.prevent="buscar" class="flex flex-wrap items-end gap-3 mb-4">
        <div>
          <label class="block text-sm font-semibold mb-1 text-gray-700">Desde</label>
          <input v-model="fechaDesde" type="date"
            class="border-gray-300 focus:border-green-500 focus:ring-green-500 rounded-lg p-2" />
        </div>
        <div>
          <label class="block text-sm font-semibold mb-1 text-gray-700">Hasta</label>
          <input v-model="fechaHasta" type="date" :min="fechaDesde || undefined"
            class="border-gray-300 focus:border-green-500 focus:ring-green-500 rounded-lg p-2" />
        </div>
        <button type="submit"
          class="px-4 py-2 bg-green-600 text-white font-semibold rounded-lg shadow hover:bg-green-700 transition">
          Buscar
        </button>
        <button v-if="hayFiltros" type="button" @click="limpiarBusqueda"
          class="px-4 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition">
          Limpiar
        </button>
        <span class="ml-auto text-sm text-gray-500">
          {{ convocatorias.total }} registro(s)
        </span>
      </form>

      <table class="min-w-full border">
        <thead class="bg-gray-100">
          <tr>
            <th class="p-2 text-left text-gray-700">Tipo</th>
            <th class="p-2 text-left text-gray-700">Fecha</th>
            <th class="p-2 text-left text-gray-700">Documentos</th>
            <th class="p-2 text-center text-gray-700">Convocatoria</th>
            <th class="p-2 text-center text-gray-700">Sesión</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="item in convocatorias.data" :key="item.id" class="border-t hover:bg-gray-50 transition">
            <td class="p-2 capitalize">{{ item.tipo_sesion }}</td>
            <td class="p-2">{{ item.fecha }}</td>

            <td class="p-2 space-y-1">
              <!-- Convocatoria -->
              <div v-if="item.convocatoria_documento">
                <a :href="urlDocumento(item, 'convocatoria_documento')" target="_blank"
                  class="text-gray-600 hover:underline">
                  Convocatoria
                </a>
              </div>
              <!-- Acta de sesión -->
              <div v-if="item.documento">
                <a :href="urlDocumento(item, 'documento')" target="_blank" class="text-green-600 hover:underline">
                  Acta
                </a>
              </div>
              <!-- Lista -->
              <div v-if="item.lista_asistencia">
                <a :href="urlDocumento(item, 'lista_asistencia')" target="_blank" class="text-gray-800 hover:underline">
                  Lista
                </a>
              </div>
              <!-- Evidencia -->
              <div v-if="item.evidencia">
                <a :href="urlDocumento(item, 'evidencia')" target="_blank" class="text-red-600 hover:underline">
                  Evidencia
                </a>
              </div>

            </td>

            <td class="p-2 text-center">
              <CheckCircleIcon v-if="item.estado_convocatoria" class="text-green-500 w-6 h-6 mx-auto" />
              <XCircleIcon v-else class="text-red-500 w-6 h-6 mx-auto" />
            </td>

            <td class="p-2 text-center">
              <CheckCircleIcon v-if="item.estado_sesion" class="text-green-500 w-6 h-6 mx-auto" />
              <XCircleIcon v-else class="text-red-500 w-6 h-6 mx-auto" />
            </td>

          </tr>

          <tr v-if="!convocatorias.data.length">
            <td colspan="5" class="p-4 text-center text-gray-500">
              {{ hayFiltros ? 'No hay convocatorias en el rango de fechas seleccionado.' : 'No hay convocatorias registradas.' }}
            </td>
          </tr>
        </tbody>
      </table>

      <!-- PAGINACIÓN (se muestra a partir de 10 registros) -->
      <div class="mt-4 flex justify-end">
        <Pagination :links="convocatorias.links" />
      </div>
    </div>

  </AuthenticatedLayout>
</template>