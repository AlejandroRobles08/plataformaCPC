<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ArrowDownTrayIcon } from '@heroicons/vue/24/solid'
import { meses, anios, anioPorDefecto, obtenerAnio, obtenerMes, exportarTablaExcel } from '@/Components/Reportes/reportes.js'

//props para recibir datos desde el controlador
const props = defineProps({
  consejo: Object,
  convocatorias: Array,
})

const anioSeleccionado = ref(anioPorDefecto())
const mesSeleccionado = ref('Enero')

//sección para pivotear convocatorias por tipo de sesión y mes
const tipoSesion = ['ordinaria', 'solemne', 'extraordinaria']
// convocatorias filtradas por año
const convocatoriasFiltradas = computed(() => (props.convocatorias ?? []).filter(c =>
  c.fecha && obtenerAnio(c.fecha) === anioSeleccionado.value
))

// Tabla anual: meses en filas y tipos de sesión en columnas.
const tablaConvocatoriasPivot = computed(() => {
  const estructura = {}
  meses.forEach(mes => {
    estructura[mes] = {}
    tipoSesion.forEach(tipo => {
      estructura[mes][tipo] = {
        convocada: 0,
        realizada: 0,
      }
    })
  })
  convocatoriasFiltradas.value.forEach(c => {
    if (!c.fecha || !c.tipo_sesion) return
    const mes = obtenerMes(c.fecha)
    const tipo = String(c.tipo_sesion).toLocaleLowerCase()
    if (!estructura[mes] || !estructura[mes][tipo]) return
    if (c.estado_convocatoria) {
      estructura[mes][tipo].convocada++
    }
    if (c.estado_sesion) {
      estructura[mes][tipo].realizada++
    }
  })
  return estructura
})

const totalesAnualesConvocatorias = computed(() => {
  const totales = {}
  tipoSesion.forEach(tipo => {
    totales[tipo] = {
      convocada: 0,
      realizada: 0,
    }
    meses.forEach(mes => {
      const datos = tablaConvocatoriasPivot.value[mes][tipo]
      totales[tipo].convocada += datos.convocada
      totales[tipo].realizada += datos.realizada
    })
  })
  return totales
})

function exportarConvocatoriasExcel() {
  exportarTablaExcel('#tabla-convocatorias-pivot',
    `convocatorias_${props.consejo.nombre}_${anioSeleccionado.value}`)
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
        Reporte de Sesiones del Consejo de Participación Ciudadana de {{ consejo.nombre }}
      </h1>

      <div v-if="!convocatorias?.length" class="text-center text-gray-500 p-10 border rounded-lg bg-gray-50">
        No hay convocatorias registradas en este consejo.
      </div>

      <div v-else>
        <!-- SELECTOR DE AÑO -->
        <div class="mb-6">
          <label class="block font-semibold mb-2">Selecciona el año</label>
          <select v-model="anioSeleccionado" class="border rounded-lg p-2 w-full sm:w-1/3">
            <option v-for="y in anios" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>

        <div class="mb-5">
          <h2 class="text-lg font-semibold">
            Convocatorias por mes
          </h2>
          <p class="text-sm text-gray-500 mt-1">
            Consejo: {{ consejo.nombre }} | Año {{ anioSeleccionado }}
          </p>
        </div>

        <!-- Selector de meses -->
        <div class="mb-5">
          <p class="text-sm text-gray-500 mb-3">
            Seleccione un mes para consultar sus datos.
          </p>

          <div class="flex flex-wrap gap-2">
            <button v-for="mes in meses" :key="mes" type="button" @click="mesSeleccionado = mes"
              :class="['px-3 py-2 rounded-lg text-sm font-medium transition-colors',
              mesSeleccionado === mes ? 'bg-blue-500 text-white' :
              'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200']">
              {{ mes.slice(0, 3) }}
            </button>
          </div>
        </div>

        <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
          <h3 class="text-lg font-semibold mb-4">
            {{ mesSeleccionado }} {{ anioSeleccionado }}
          </h3>

          <!-- Tabla del mes seleccionado -->
          <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table id="tabla-convocatorias-pivot" class="w-full border-collapse text-sm">
              <thead class="bg-gray-100 dark:bg-gray-800">
                <tr>
                  <th class="px-3 py-3 text-left">
                    Tipo de sesión
                  </th>
                  <th class="px-3 py-3 text-center">
                    Convocadas
                  </th>
                  <th class="px-3 py-3 text-center">
                    Realizadas
                  </th>
                </tr>
              </thead>

              <tbody>
                <tr v-for="tipo in tipoSesion" :key="tipo" class="border-t border-gray-200 dark:border-gray-700">
                  <td class="px-3 py-3 font-medium capitalize">
                    {{ tipo }}
                  </td>
                  <td class="px-3 py-3 text-center">
                    {{ tablaConvocatoriasPivot[mesSeleccionado][tipo].convocada }}
                  </td>
                  <td class="px-3 py-3 text-center">
                    {{ tablaConvocatoriasPivot[mesSeleccionado][tipo].realizada }}
                  </td>
                </tr>
              </tbody>

              <tfoot class="bg-gray-100 font-semibold dark:bg-gray-800">
                <tr>
                  <td class="px-3 py-3">Total del mes</td>
                  <td class="px-3 py-3 text-center">
                    {{ tipoSesion.reduce((total, tipo) =>
                    total + tablaConvocatoriasPivot[mesSeleccionado][tipo].convocada, 0) }}
                  </td>
                  <td class="px-3 py-3 text-center">
                    {{ tipoSesion.reduce((total, tipo) =>
                    total + tablaConvocatoriasPivot[mesSeleccionado][tipo].realizada, 0) }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>

          <!-- Resumen anual desplegable -->
          <details class="mt-4 rounded-lg border border-gray-200 dark:border-gray-700">
            <summary class="cursor-pointer px-4 py-3 font-semibold">
              Consultar totales anuales de {{ anioSeleccionado }}
            </summary>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="bg-gray-100 dark:bg-gray-800">
                  <tr>
                    <th class="px-3 py-3 text-left">Tipo de sesión</th>
                    <th class="px-3 py-3 text-center">Convocadas</th>
                    <th class="px-3 py-3 text-center">Realizadas</th>
                  </tr>
                </thead>

                <tbody>
                  <tr v-for="tipo in tipoSesion" :key="tipo" class="border-t border-gray-200 dark:border-gray-700">
                    <td class="px-3 py-3 capitalize">{{ tipo }}</td>
                    <td class="px-3 py-3 text-center">
                      {{ totalesAnualesConvocatorias[tipo].convocada }}
                    </td>
                    <td class="px-3 py-3 text-center">
                      {{ totalesAnualesConvocatorias[tipo].realizada }}
                    </td>
                  </tr>
                </tbody>

                <tfoot class="bg-gray-100 font-semibold dark:bg-gray-800">
                  <tr>
                    <td class="px-3 py-3">Total anual</td>
                    <td class="px-3 py-3 text-center">
                      {{ tipoSesion.reduce((total, tipo) =>
                      total + totalesAnualesConvocatorias[tipo].convocada, 0) }}
                    </td>
                    <td class="px-3 py-3 text-center">
                      {{ tipoSesion.reduce((total, tipo) =>
                      total + totalesAnualesConvocatorias[tipo].realizada, 0) }}
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </details>
        </div>

        <div class="flex justify-end mt-4">
          <button @click="exportarConvocatoriasExcel"
            class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center">
            <ArrowDownTrayIcon class="w-5 h-5 mr-2" />
            Exportar a Excel
          </button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
