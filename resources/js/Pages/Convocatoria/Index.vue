<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useForm, Link, router } from '@inertiajs/vue3'
import Pagination from '@/Components/Pagination.vue'
import InputError from '@/Components/InputError.vue'
import { CheckCircleIcon, XCircleIcon, BookmarkIcon, ArrowUpTrayIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/solid'
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

// Documentos obligatorios: los de sesión solo se piden si se marca "Sesión realizada"
const DOCUMENTOS = [
  { campo: 'convocatoria_documento', nombre: 'Acta de convocatoria', enlace: 'Convocatoria', color: 'text-gray-600', mensaje: 'El acta de convocatoria es obligatoria; debes adjuntarla.' },
  { campo: 'documento', nombre: 'Acta de sesión', enlace: 'Acta', color: 'text-green-600', mensaje: 'El acta de sesión es obligatoria; debes adjuntarla.', sesion: true },
  { campo: 'lista_asistencia', nombre: 'Lista de asistencia', enlace: 'Lista', color: 'text-gray-800', mensaje: 'La lista de asistencia es obligatoria; debes adjuntarla.', sesion: true },
  { campo: 'evidencia', nombre: 'Evidencia fotográfica', enlace: 'Evidencia', color: 'text-red-600', mensaje: 'La evidencia fotográfica es obligatoria; debes adjuntarla.', sesion: true },
]

const documentosVisibles = computed(() => DOCUMENTOS.filter((doc) => !doc.sesion || form.estado_sesion))

watch(() => form.estado_convocatoria, (val) => {
  if (!val) form.estado_sesion = false
  else form.clearErrors('estado_convocatoria')
})

function seleccionarArchivo(campo, e) {
  form[campo] = e.target.files[0]
  form.clearErrors(campo)
}

function validarFormulario() {
  form.clearErrors()
  if (!form.estado_convocatoria) form.setError('estado_convocatoria', 'Por favor selecciona la casilla "Convocatoria realizada" para poder guardar.')
  else documentosVisibles.value.filter((doc) => !form[doc.campo]).forEach((doc) => form.setError(doc.campo, doc.mensaje))
  return !form.hasErrors
}

function submit() {
  if (!validarFormulario()) return

  form.post(route('convocatorias.store', { consejo: props.consejo.id }), {
    forceFormData: true,
    onSuccess: () => {
      Swal.fire({ icon: 'success', title: 'Convocatoria registrada', text: 'La convocatoria se guardó correctamente.', confirmButtonColor: '#16a34a' })
      form.reset()
    },
    onError: (errors) => Swal.fire({ icon: 'error', title: 'Error', text: Object.values(errors)[0] || 'No se pudo registrar la convocatoria.' }),
  })
}

// Buscador por fecha (filtra en el servidor y conserva la paginación)
const fechaDesde = ref(props.filtros?.fecha_desde ?? '')
const fechaHasta = ref(props.filtros?.fecha_hasta ?? '')
const hayFiltros = computed(() => !!(props.filtros?.fecha_desde || props.filtros?.fecha_hasta))

function buscar() {
  router.get(route('convocatorias.index', { consejo: props.consejo.id }),
    { fecha_desde: fechaDesde.value || undefined, fecha_hasta: fechaHasta.value || undefined },
    { preserveState: true, preserveScroll: true, replace: true })
}

function limpiarBusqueda() {
  fechaDesde.value = fechaHasta.value = ''
  buscar()
}
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

      <form @submit.prevent="submit" class="space-y-4">

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
        <InputError :message="form.errors.estado_convocatoria" class="!mt-1" />

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
        <div v-if="form.estado_convocatoria">
          <h2 class="text-lg font-semibold mb-2">Lista de documentos</h2>

          <div class="flex items-start gap-2 mb-4 p-3 rounded-lg border border-amber-300 bg-amber-50 text-sm text-amber-800">
            <ExclamationTriangleIcon class="w-5 h-5 flex-shrink-0 text-amber-500" />
            <span>Todos los documentos de esta lista son obligatorios. Debes adjuntarlos en formato PDF para poder guardar.</span>
          </div>

          <div class="space-y-4">
            <div v-for="doc in documentosVisibles" :key="doc.campo">
              <label class="block font-semibold mb-1 text-gray-700">{{ doc.nombre }} <span class="text-red-600">*</span></label>
              <label class="flex items-center border rounded-lg p-2 bg-gray-50 hover:bg-gray-100 cursor-pointer transition text-gray-700"
                :class="form.errors[doc.campo] ? 'border-red-500' : 'border-gray-300'">
                <ArrowUpTrayIcon class="w-5 h-5 text-green-600 mr-2" />
                <span>{{ form[doc.campo]?.name ?? 'Seleccionar archivo...' }}</span>
                <input type="file" @change="seleccionarArchivo(doc.campo, $event)" class="hidden" accept=".pdf" />
              </label>
              <InputError :message="form.errors[doc.campo]" class="mt-1" />
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
              <a v-for="doc in DOCUMENTOS.filter((d) => item[d.campo])" :key="doc.campo"
                :href="route('convocatorias.documento', { convocatoria: item.id, campo: doc.campo })" target="_blank"
                class="block w-fit hover:underline" :class="doc.color">
                {{ doc.enlace }}
              </a>
            </td>

            <td v-for="estado in ['estado_convocatoria', 'estado_sesion']" :key="estado" class="p-2 text-center">
              <CheckCircleIcon v-if="item[estado]" class="text-green-500 w-6 h-6 mx-auto" />
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
