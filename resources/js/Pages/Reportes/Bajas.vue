<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { formatearFecha } from '@/Components/Reportes/reportes.js'

//props para recibir datos desde el controlador
const props = defineProps({
  consejo: Object,
  bajas: Array,
})

const busqueda = ref('')
//quita mayúsculas y acentos de la cadena de búsqueda
function normalizar(texto) {
  return String(texto ?? '').normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase()
}
//nombres legibles de cada motivo de baja
const MOTIVOS = {
  inasistencia: 'Inasistencia',
  sancion: 'Sanción',
  fin_periodo: 'Fin de periodo',
  renuncia: 'Renuncia',
  error_registro: 'Error de registro',
}
function etiquetaMotivo(motivo) {
  return MOTIVOS[motivo] ?? motivo
}

//filtra por nombre, motivo o fecha
const bajasFiltradas = computed(() => {
  const texto = normalizar(busqueda.value.trim())
  const bajas = props.bajas ?? []
  if (!texto) return bajas
  return bajas.filter((baja) => normalizar(
`${baja.nombre} ${baja.apellido} ${etiquetaMotivo(baja.motivo)} ${formatearFecha(baja.fecha_baja)}`
  ).includes(texto))
})

function obtenerNombreArchivo(ruta) {
  if (!ruta) return '-'
  return ruta.split('/').pop()
}

function obtenerUrlPublica(ruta) {
  if (!ruta) return '#'
  return `/storage/${ruta}`
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
        Bajas de integrantes del Consejo de Participación Ciudadana de {{ consejo.nombre }}
      </h1>

      <!-- Buscador -->
      <div class="mb-4">
        <input v-model="busqueda" type="search" placeholder="Buscar por nombre, motivo o fecha"
          class="w-full rounded border-gray-300 px-3 py-2 text-sm focus:border-gray-600 focus:ring-gray-600" />
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full border text-sm">
          <thead class="bg-gray-100">
            <tr>
              <th class="p-2 text-left">Integrante</th>
              <th class="p-2 text-left">Motivo</th>
              <th class="p-2 text-left">Fecha</th>
              <th class="p-2 text-left">Evidencia</th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="!bajasFiltradas.length">
              <td colspan="4" class="p-4 text-center text-gray-500">
                {{ busqueda.trim()
                  ? 'No hay bajas que coincidan con la búsqueda.'
                  : 'No hay integrantes dados de baja en este consejo.' }}
              </td>
            </tr>

            <tr v-for="baja in bajasFiltradas" :key="baja.id" class="border-t">
              <td class="p-2">{{ baja.nombre }} {{ baja.apellido }}</td>
              <td class="p-2">{{ etiquetaMotivo(baja.motivo) }}</td>
              <td class="p-2">{{ formatearFecha(baja.fecha_baja) }}</td>
              <td class="p-2">
                <a v-if="baja.evidencia_pdf" :href="obtenerUrlPublica(baja.evidencia_pdf)" target="_blank"
                  class="text-red-700 underline">
                  {{ obtenerNombreArchivo(baja.evidencia_pdf) }}
                </a>
                <span v-else class="text-gray-500">
                  {{ baja.motivo === 'error_registro' ? 'No aplica' : 'Sin archivo' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
