<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link } from '@inertiajs/vue3'
import { ArrowDownTrayIcon } from '@heroicons/vue/24/solid'
import { exportarTablaExcel } from '@/Components/Reportes/reportes.js'

//props para recibir datos desde el controlador
const props = defineProps({
  consejo: Object,
  integrantes: Array,
})

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

function exportarDocumentosExcel() {
  exportarTablaExcel('#tabla-documentos', `documentos_${props.consejo.nombre}`)
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

      <div v-if="!integrantes?.length" class="text-center text-gray-500 p-10 border rounded-lg bg-gray-50">
        No hay integrantes registrados en este consejo.
      </div>

      <div v-else>
        <div class="overflow-x-auto">
          <table id="tabla-documentos" class="min-w-full border text-sm">
            <thead class="bg-gray-100">
              <tr>
                <th class="p-2 text-left">Integrante</th>
                <th v-for="doc in tiposDocumentos" :key="doc.key" class="p-2 text-center">
                  {{ doc.label }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="integrante in integrantes" :key="integrante.id" class="border-t">
                <td class="p-2">{{ integrante.nombre }} {{ integrante.apellido }}</td>
                <td v-for="doc in tiposDocumentos" :key="doc.key" class="p-2 text-center">
                  <span v-if="tieneDocumento(integrante, doc.key)" class="text-green-700 font-semibold">si</span>
                  <span v-else class="text-red-700 font-semibold">no</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="text-right mt-4">
          <button @click="exportarDocumentosExcel"
            class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 flex ml-auto">
            <ArrowDownTrayIcon class="w-5 h-5 mr-2" />
            Exportar a Excel
          </button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
