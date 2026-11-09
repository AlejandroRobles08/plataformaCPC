<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { ArrowDownTrayIcon } from '@heroicons/vue/24/solid'
import Pagination from '@/Components/Pagination.vue'
import { exportarTablaExcel } from '@/Components/Reportes/reportes.js'

//props para recibir datos desde el controlador
const props = defineProps({
  consejo: Object,
  integrantes: Object,
  integrantesExportacion: Array,
  filtros: Object,
})

const exportando = ref(false)

// Buscador: filtra en el servidor porque la tabla solo recibe la página actual
const busqueda = ref(props.filtros?.busqueda ?? '')
let temporizadorBusqueda = null

watch(busqueda, (valor) => {
  clearTimeout(temporizadorBusqueda)
  temporizadorBusqueda = setTimeout(() => {
    router.get(
      route('reportes.documentos', props.consejo.id),
      { busqueda: valor.trim() || undefined },
      { preserveState: true, preserveScroll: true, replace: true }
    )
  }, 300)
})

// Evita que una búsqueda pendiente regrese a esta página después de salir de ella
onBeforeUnmount(() => clearTimeout(temporizadorBusqueda))

// documentos
const tiposDocumentos = [
  { label: 'INE', key: 'ine' },
  { label: 'Comprobante domiciliario', key: 'comprobante_domiciliario' },
  { label: 'Bajo protesta (Art. 170)', key: 'bajo_protesta_art_170' },
  { label: 'Curriculum Vitae', key: 'curriculum_vitae' },
  { label: 'Integración de fórmula', key: 'integracion_formula' },
  { label: 'Carta de motivos', key: 'carta_motivos' },
  { label: 'Cumplimiento de normatividad', key: 'cumplimiento_normatividad' },
]

function tieneDocumento(integrante, key) {
  if (!integrante || !Array.isArray(integrante.documentos)) return false
  return integrante.documentos.some(doc => doc.tipo === key)
}

// La tabla visible solo tiene la página actual: el Excel se arma con todos los integrantes
function exportarDocumentosExcel() {
  router.reload({
    only: ['integrantesExportacion'],
    onStart: () => { exportando.value = true },
    onSuccess: async () => {
      await nextTick()
      exportarTablaExcel('#tabla-documentos', `documentos_${props.consejo.nombre}`)
    },
    onFinish: () => { exportando.value = false },
  })
}
</script>

<template>
  <AuthenticatedLayout>
    <div class="mt-2 mb-4">
      <Link :href="route('reportes.consejo', consejo.id)" class="text-gray-600 hover:underline">
        ← Volver a Reportes
      </Link>
    </div>
    <div class="p-6 bg-white rounded-lg shadow-md">

      <h1 class="text-2xl font-bold mb-6">
        Checklist de Documentos del Consejo de Participación Ciudadana de {{ consejo.nombre }}
      </h1>

      <!-- Buscador -->
      <div v-if="integrantes.total || filtros?.busqueda" class="mb-4">
        <input v-model="busqueda" type="search" maxlength="100" placeholder="Buscar por nombre o apellidos"
          class="w-full rounded border-gray-300 px-3 py-2 text-sm focus:border-gray-600 focus:ring-gray-600" />
      </div>

      <div v-if="!integrantes.total" class="text-center text-gray-500 p-10 border rounded-lg bg-gray-50">
        {{ filtros?.busqueda
          ? 'No hay integrantes que coincidan con la búsqueda.'
          : 'No hay integrantes registrados en este consejo.' }}
      </div>

      <div v-else>
        <!-- Tabla oculta con todos los integrantes, solo para exportar a Excel -->
        <table v-if="integrantesExportacion" id="tabla-documentos" class="hidden">
          <thead>
            <tr>
              <th>Integrante</th>
              <th v-for="doc in tiposDocumentos" :key="doc.key">{{ doc.label }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="integrante in integrantesExportacion" :key="integrante.id">
              <td>{{ integrante.nombre }} {{ integrante.apellido }}</td>
              <td v-for="doc in tiposDocumentos" :key="doc.key">
                {{ tieneDocumento(integrante, doc.key) ? 'si' : 'no' }}
              </td>
            </tr>
          </tbody>
        </table>

        <div class="overflow-x-auto">
          <table class="min-w-full border text-sm">
            <thead class="bg-gray-100">
              <tr>
                <th class="p-2 text-left">Integrante</th>
                <th v-for="doc in tiposDocumentos" :key="doc.key" class="p-2 text-center">
                  {{ doc.label }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="integrante in integrantes.data" :key="integrante.id" class="border-t">
                <td class="p-2">{{ integrante.nombre }} {{ integrante.apellido }}</td>
                <td v-for="doc in tiposDocumentos" :key="doc.key" class="p-2 text-center">
                  <span v-if="tieneDocumento(integrante, doc.key)" class="text-green-700 font-semibold">si</span>
                  <span v-else class="text-red-700 font-semibold">no</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- PAGINACIÓN (se muestra a partir de 10 registros) -->
        <div class="mt-4 flex justify-end">
          <Pagination :links="integrantes.links" />
        </div>

        <div class="text-right mt-4">
          <button @click="exportarDocumentosExcel" :disabled="exportando"
            class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 flex ml-auto disabled:opacity-50">
            <ArrowDownTrayIcon class="w-5 h-5 mr-2" />
            Exportar a Excel
          </button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
