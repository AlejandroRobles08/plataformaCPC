<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, computed, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ArrowDownTrayIcon } from '@heroicons/vue/24/solid'
import { meses, anios, anioPorDefecto, obtenerAnio, obtenerMes, exportarTablaExcel } from '@/Components/Reportes/reportes.js'

//props para recibir datos desde el controlador
const props = defineProps({
  consejo: Object,
  asistencias: Array,
})
const anioSeleccionado = ref(anioPorDefecto())
const mesSeleccionado = ref('Enero')

//al cambiar el año, se regresará al primer mes
watch(anioSeleccionado, () => { mesSeleccionado.value = 'Enero' })

//simbología: se usa en la leyenda y en ambas tablas
const simbolos = [
  { clave: 'A', nombre: 'Asistencia', columna: 'Asistencias', clase: 'bg-green-100 text-green-800' },
  { clave: 'I', nombre: 'Inasistencia', columna: 'Inasistencias', clase: 'bg-yellow-100 text-yellow-800' },
  { clave: 'IJ', nombre: 'Inasistencia Justificada', columna: 'Justificadas', clase: 'bg-orange-100 text-orange-800' },
]
const claseSimbolo = Object.fromEntries(simbolos.map(s => [s.clave, s.clase]))
const sumar = (filas, fn) => filas.reduce((total, fila) => total + fn(fila), 0)
const porSimbolo = fn => Object.fromEntries(simbolos.map(({ clave }) => [clave, fn(clave)]))

//agrupación por integrante del año seleccionado, conservando cada registro mensual
const asistenciasExcel = computed(() => {
  const mapa = {}
  ;(props.asistencias ?? []).forEach(a => {
    if (!a.integrante || !a.fecha || obtenerAnio(a.fecha) !== anioSeleccionado.value) return
    const mes = obtenerMes(a.fecha)
    if (!meses.includes(mes)) return

    const fila = mapa[a.integrante.id] ??= {
      id: a.integrante.id,
      integrante: [a.integrante.nombre, a.integrante.apellido_paterno, a.integrante.apellido_materno]
        .filter(Boolean).join(' '),
      cargo: a.integrante.cargo ?? '',
      meses: Object.fromEntries(meses.map(m => [m, []])),
      totales: porSimbolo(() => 0),
    }
    const simbolo = String(a.simbolo ?? '').toLocaleUpperCase()
    fila.meses[mes].push(simbolo)
    if (simbolo in fila.totales) fila.totales[simbolo]++
  })
  return Object.values(mapa)
})

//registros del mes seleccionado, separados por símbolo
const asistenciasDelMes = computed(() => asistenciasExcel.value.map(fila => {
  const registro = fila.meses[mesSeleccionado.value]
  return { ...fila, registro, simbolos: porSimbolo(clave => registro.filter(s => s === clave)) }
}))

//totales de ambas tablas
const totalMes = computed(() => ({
  sesiones: sumar(asistenciasDelMes.value, f => f.registro.length),
  ...porSimbolo(clave => sumar(asistenciasDelMes.value, f => f.simbolos[clave].length)),
}))
const totalAnual = computed(() => ({
  ...Object.fromEntries(meses.map(m => [m, sumar(asistenciasExcel.value, f => f.meses[m].length)])),
  ...porSimbolo(clave => sumar(asistenciasExcel.value, f => f.totales[clave])),
}))

//exportación a excel
function exportarAsistenciasExcel() {
  exportarTablaExcel('#tabla-asistencia-anual',
    `asistencias_${props.consejo.nombre}_${anioSeleccionado.value}`)
}
</script>


<template>
  <AuthenticatedLayout>
    <div class="mt-2 mb-4">
      <Link :href="route('reportes.consejo', consejo.id)" class="text-gray-600 hover:underline">
         ← Volver a Reportes
      </Link>   
    </div>

    <div class="p-6 bg-white text-gray-700 rounded-lg shadow-md">
      <h1 class="text-2xl font-bold mb-6">
        Reporte de Asistencia del Consejo de Participación Ciudadana de {{ consejo.nombre }}
      </h1>

      <div v-if="!asistencias?.length" class="text-center text-gray-500 p-10 border border-gray-200 rounded-lg">
        No hay asistencias registradas en este consejo.
      </div>
      <div v-else>
        <!-- FILTRO DE AÑO -->
         <div class="mb-6">
          <label class="block font-semibold mb-2">
            Selecciona el año
          </label>
          <select v-model="anioSeleccionado" class="border border-gray-300 bg-white text-gray-700 rounded-lg p-2 w-full sm:w-1/3">
            <option v-for="y in anios" :key="y" :value="y">
              {{ y }}
            </option>
          </select>
         </div>

        <!-- SIMBOLOGÍA de abreviaturas -->
        <div class="mb-5 rounded-lg border border-gray-200 p-4 flex flex-wrap items-center gap-x-6 gap-y-3">
          <span class="font-semibold">Simbología:</span>
          <div v-for="s in simbolos" :key="s.clave" class="flex items-center gap-2">
            <span :class="['rounded px-3 py-1 font-bold', s.clase]">{{ s.clave }}</span>
            <span>{{ s.nombre }}</span>
          </div>
        </div>

        <!-- vista mensual -->
        <section class="mb-6 rounded-lg border border-gray-200 p-4">
          <h2 class="text-xl font-bold mb-1">Asistencia por mes</h2>
          <p class="text-sm text-gray-500 mb-4">Consejo: {{ consejo.nombre }} | Año {{ anioSeleccionado }}</p>
          <p class="text-sm text-gray-500 mb-3">Seleccione algún mes para consultar asistencias de los integrantes.</p>

          <!-- selector de meses -->
          <div class="flex flex-wrap gap-2 mb-5">
            <button v-for="mes in meses" :key="mes" type="button" @click="mesSeleccionado = mes"
              :class="['px-3 py-2 rounded-lg text-sm font-medium transition-colors', mesSeleccionado === mes
                ? 'bg-orange-700 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']">
              {{ mes }}
            </button>
          </div>

          <div class="border-t border-gray-200 pt-5">
            <h3 class="text-lg font-semibold mb-4">{{ mesSeleccionado }} {{ anioSeleccionado }}</h3>
            <div class="overflow-x-auto rounded-lg border border-gray-200">
              <table class="w-full text-sm border-collapse">
                <thead class="bg-gray-50 text-gray-600">
                  <tr>
                    <th class="border border-gray-200 p-3 text-left">Integrante</th>
                    <th class="border border-gray-200 p-3 text-left">Cargo</th>
                    <th class="border border-gray-200 p-3 text-center">Sesion del mes</th>
                    <th v-for="s in simbolos" :key="s.clave" class="border border-gray-200 p-3 text-center">{{ s.columna }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="asistenciasDelMes.length === 0">
                    <td colspan="6" class="p-4 text-center text-gray-500">No hay asistencias registradas en {{ mesSeleccionado }}.</td>
                  </tr>
                  <tr v-for="fila in asistenciasDelMes" :key="fila.id" class="border-t border-gray-200">
                    <td class="p-3 font-medium">{{ fila.integrante }}</td>
                    <td class="p-3">{{ fila.cargo }}</td>
                    <td class="p-3 text-center">{{ fila.registro.length }}</td>
                    <td v-for="s in simbolos" :key="s.clave" class="p-3 text-center">
                      <span class="inline-flex flex-wrap justify-center gap-1">
                        <span v-for="(simbolo, i) in fila.simbolos[s.clave]" :key="i" :class="['rounded px-2 py-1', s.clase]">{{ simbolo }}</span>
                        <span v-if="!fila.simbolos[s.clave].length">0</span>
                      </span>
                    </td>
                  </tr>
                </tbody>
                <tfoot class="bg-gray-50 font-semibold">
                  <tr>
                    <td colspan="2" class="border border-gray-200 p-3">Total del mes</td>
                    <td class="border border-gray-200 p-3 text-center">{{ totalMes.sesiones }}</td>
                    <td v-for="s in simbolos" :key="s.clave" class="border border-gray-200 p-3 text-center">{{ totalMes[s.clave] }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </section>

        <!-- vista anual -->
        <section class="rounded-lg border border-gray-200 p-4">
          <h2 class="text-xl font-bold mb-1">Resumen anual de asistencias del año ({{ anioSeleccionado }})</h2>
          <p class="text-sm text-gray-500 mb-4">Registros de cada integrante durante el año</p>

          <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table id="tabla-asistencia-anual" class="w-full text-sm border-collapse">
              <thead class="bg-gray-50 text-gray-600">
                <tr>
                  <th class="border border-gray-200 p-2 text-left">Integrante</th>
                  <th class="border border-gray-200 p-2 text-left">Cargo</th>
                  <th v-for="mes in meses" :key="mes" class="border border-gray-200 p-2 text-center">{{ mes.slice(0, 3) }}</th>
                  <th v-for="s in simbolos" :key="s.clave" class="border border-gray-200 p-2 text-center">{{ s.clave }} totales</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="asistenciasExcel.length === 0">
                  <td :colspan="meses.length + 5" class="p-4 text-center text-gray-500">No hay asistencias registradas en {{ anioSeleccionado }}</td>
                </tr>
                <tr v-for="fila in asistenciasExcel" :key="fila.id" class="border-t border-gray-200">
                  <td class="border border-gray-200 p-2 font-medium">{{ fila.integrante }}</td>
                  <td class="border border-gray-200 p-2">{{ fila.cargo }}</td>
                  <td v-for="mes in meses" :key="mes" class="relative border border-gray-200 p-2 text-center whitespace-nowrap">
                    <!-- relative' en la celda evita que, al ser absolute, desborde la página -->
                    <span v-for="(simbolo, i) in fila.meses[mes]" :key="i"><span v-if="i" class="sr-only">, </span><span
                      :class="['inline-block rounded px-1.5 py-0.5 mx-0.5 font-semibold', claseSimbolo[simbolo]]">{{ simbolo }}</span></span>
                    <span v-if="!fila.meses[mes].length">-</span>
                  </td>
                  <td v-for="s in simbolos" :key="s.clave" class="border border-gray-200 p-2 text-center">{{ fila.totales[s.clave] }}</td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50 font-semibold">
                <tr>
                  <td colspan="2" class="border border-gray-200 p-2">Total anual</td>
                  <td v-for="mes in meses" :key="mes" class="border border-gray-200 p-2 text-center">{{ totalAnual[mes] }}</td>
                  <td v-for="s in simbolos" :key="s.clave" class="border border-gray-200 p-2 text-center">{{ totalAnual[s.clave] }}</td>
                </tr>
              </tfoot>
            </table>
          </div>

            <div class="flex justify-end mt-4">
              <button @click="exportarAsistenciasExcel" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center">
                <ArrowDownTrayIcon class="w-5 h-5 mr-2" />
                Exportar a Excel
              </button>
            </div>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

