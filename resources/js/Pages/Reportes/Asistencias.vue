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
watch(anioSeleccionado, () => {
  mesSeleccionado.value = 'Enero'
})

// asistencias filtradas por año
const asistenciasFiltradas = computed(() =>
  (props.asistencias ?? []).filter(a =>
    a.fecha && obtenerAnio(a.fecha) === anioSeleccionado.value))

//agrupación por integrante, conservando cada registro mensual
const asistenciasExcel = computed(() => {
  const mapa = {}
  asistenciasFiltradas.value.forEach(a => {
    if (!a.integrante || !a.fecha) return
    const id = a.integrante.id
    const mes = obtenerMes(a.fecha)

    if (!meses.includes(mes)) return

    if (!mapa[id]) {
      mapa[id] = {
        integrante: `${a.integrante.nombre} ${a.integrante.apellido}`,
        puesto: a.integrante.puesto ?? '',
        meses: Object.fromEntries(meses.map(m=> [m, []])),
        totalA: 0,
        totalI: 0,
        totalIJ: 0,
      }
    }
    const simbolo = String(a.simbolo ?? '').toLocaleUpperCase()
    mapa[id].meses[mes].push(simbolo)
    if (simbolo === 'A') mapa[id].totalA++
    if (simbolo === 'I') mapa[id].totalI++
    if (simbolo === 'IJ') mapa[id].totalIJ++
  })
  return Object.values(mapa)
})

//Registros del mes seleccionado
const asistenciasDelMes = computed(() => asistenciasExcel.value.map(fila => ({
  ...fila, registroMes: fila.meses[mesSeleccionado.value] ?? [],
  totalMes: (fila.meses[mesSeleccionado.value] ?? []).length,
  asistenciasMes: (fila.meses[mesSeleccionado.value] ?? [])
    .filter(s => s === 'A').length,
  inasistenciaMes: (fila.meses[mesSeleccionado.value] ?? [])
    .filter(s => s === 'I').length,
  justificadasMes: (fila.meses[mesSeleccionado.value] ?? [])
    .filter(s => s === 'IJ').length,
  }))
)

//Totales de la tabla mensual
const totalMes = computed(() => {
  return asistenciasDelMes.value.reduce((totales, fila) => {
    totales.sesiones += fila.totalMes
    totales.A += fila.asistenciasMes
    totales.I += fila.inasistenciaMes
    totales.IJ += fila.justificadasMes
    return totales
  }, {sesiones: 0, A:0, I:0, IJ:0})
})

//totales de sesiones al mes
const totalesMensualesAnuales = computed(() => {
  const totales = Object.fromEntries(meses.map(m => [m, 0]))
  asistenciasExcel.value.forEach(fila => {
    meses.forEach(mes => {
      totales[mes] += fila.meses[mes].length
    })
  })
  return totales
})

//exportación a excel
function exportarAsistenciasExcel() {
  exportarTablaExcel('#tabla-asistencias-excel',
    `asistencias_${props.consejo.nombre}_${anioSeleccionado.value}`)
}
</script>


<template>
  <AuthenticatedLayout>
    <div class="mt-2 mb-4">
      <Link :href="route('reportes.consejo', 'consejo.id')" class="text-gray-600 hover:underline">
         ← Volver a Reportes
      </Link>   
    </div>

    <div class="p-6 bg- rounded-lg shadow-md">
      <h1 class="text-2xl font-bold mb-6">
        Reporte de Asistencia del Consejo de Participación Ciudadana de {{ consejo.nombre }}
      </h1>

      <div v-if="!asistencias?.length" class="text-center text-gray-500 p-10 border rounded-lg">
        No hay asistencias registradas en este consejo.
      </div>
      <div v-else>
        <!-- FILTRO DE AÑO -->
         <div class="mb-6">
          <label class="block font-sembibold mb-2">
            Selecciona el año
          </label>
          <select v-model="anioSeleccionado" class="border rounded-lg p-2 w-full sm:w-1/3">
            <option v-for="y in anios" :key="y" :value="y">
              {{ y }}
            </option>
          </select>
         </div>

        <!-- SIMBOLOGÍA de abreviaturas -->
         <div class="mb-5 rounded-lg border border-gray-300 dark:border-gray-700 p-4">
          <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
            <span class="font-semibold">Simbología:</span>

            <div class="flex items-center gap-2">
              <span class="inline-flex items-center justify-center rounded px-3 py-1 bg-green-600 text-white font-bold">
                A
              </span>
              <span>Asistencia</span>
            </div>

            <div class="flex items-center gap-2">
              <span class="inline-flex items-center justify-center rounded px-3 py-1 bg-yellow-500 text-white font-bold">
                I
              </span>
              <span>Inasistencia</span>
            </div>

            <div class="flex items-center gap-2">
              <span class="inline-flex items-center justify-center rounded px-3 py-1 bg-orange-500 text-white font-bold">
                IJ
              </span>
              <span>Inasistencia Justificada</span>
            </div>
          </div>
         </div>

         <!-- vista menusal -->
          <section class="mb-6 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <h2 class="text-xl font-bold mb-1">
              Asistencia por mes
            </h2>

            <p class="text-sm text-gray-500 mb-4">
              Consejo: {{ consejo.nombre }} | Año {{ anioSeleccionado }}
            </p>

            <p class="text-sm text-gray-500 mb-3">
              Seleccione algun mes para conusltar asistencias de los integrantes. 
            </p>
            <!--selector de meses completop -->
            <div class="flex flex-wrap gap-2 mb-5">
              <button v-for="mes in meses" :key="mes" type="button" @click="mesSeleccionado = mes" 
              :class="['px-3 py-2 rounded-lg text-sm font-medium transition-colors', mesSeleccionado === mes 
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-200 dark:bg-gray800 dark:text-gray-200']">
                {{ mes }}
              </button>
            </div>

            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
              <h3 class="text-lg font-semibold mb-4">
                {{ mesSeleccionado }} {{ anioSeleccionado }}
              </h3>
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
              <table class="w-full text-sm border-collapse">
                <thead class="bg-gray-100 dark:bg-gray-800">
                  <tr>
                    <th class="border p-3 text-left">Integrante</th>
                    <th class="border p-3 text-left">Cargo</th>
                    <th class="border p-3 text-center">Sesion del mes</th>
                    <th class="border p-3 text-center">Asistencias</th>
                    <th class="border p-3 text-center">Inasistencias</th>
                    <th class="border p-3 text-center">Justificadas</th>
                  </tr>
                </thead>

                <tbody>
                  <tr v-if="asistenciasDelMes.length === 0">
                    <td colspan="6" class="p-4 text-center text-gray-500">
                      No hay asistencias registradas en {{ mesSeleccionado }}.
                    </td>
                  </tr>

                  <tr v-for="fila in asistenciasDelMes" :key="fila.id" class="border-t border-gray-200 dark:border-gray-700">
                    <td class="p-3 font-medium">{{ fila.integrante }}</td>
                    <td class="p-3">{{ fila.puesto }}</td>
                    <td class="p-3 text-center">{{ fila.totalMes }}</td>
                    <td class="p-3 text-center">
                      <span class="inline-flex flex-wrap justify-center gap-1">
                        <span v-for="(simbolo, i) in fila.registroMes.filter(s => s === 'A')" :key="`a-$i`"
                          class="rounded px-2 py-1 bg-green-600 text-white">
                          {{ simbolo }}
                        </span>
                        <span v-if="fila.asistencias === 0">0</span>
                      </span>
                    </td>

                    <td class="p-3 text-center">
                      <span class="inline-flex flex-wrap justify-center gap-1">
                        <span v-for="(simbolo, i) in fila.registroMes.filter(s => s === 'I')"
                          :key="`i-${{i}}`" class="roundend px-2 py-1 bg-green-600 text-white">
                          {{ simbolo }}
                        </span>
                        <span v-if="fila.asistencias === 0">0</span>
                      </span>
                    </td>

                    <td class="p-3 text-center">
                      <span class="inline-flex flex-wrap justify-center gap-1">
                        <span v-for="(simbolo, i) in fila.registroMes.filter(s => s === 'IJ')"
                          :key="`ij-${{i}}`" class="roundend px-2 py-1 bg-green-600 text-white">
                          {{ simbolo }}
                        </span>
                        <span v-if="fila.asistencias === 0">0</span>
                      </span>
                    </td>
                  </tr>
                </tbody>

                <tfoot class="bg-gray-100 font-semibold dark:bg-gray-500">
                  <tr>
                    <td colspan="2" class="border p-3">Total de meses</td>
                    <td class="border p-3 text-center">{{ totalesMes.sesiones }}</td>
                    <td class="border p-3 text-center">{{ totalesMes.A }}</td>
                    <td class="border p-3 text-center">{{ totalesMes.I }}</td>
                    <td class="border p-3 text-center">{{ totalesMes.IJ }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
            </div>
          </section>

          <!-- Vista anual -->
           <section class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <h2 class="text-xl font-bold mb-1">
              Resumen anual de asistencias del año ({{ anioSeleccionado }})
            </h2>
            <p class="tetx-sm text-gray-500 mb-4">
              Registros de cada integrante durante el año
            </p>

            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
              <table id="tabla-asistencia-anual" class="w-full text-sm border-collapse">
                <thead class="bg-gray-100 dark:bg-gray-800">
                  <tr>
                    <th class="border p-2 text-left">Integrante</th>
                    <th class="border p-2 text-left">Cargo</th>
                    <th v-for="mes in meses" :key="mes" class="border p-2 text-center">
                      {{ mes.slice(0, 3) }}
                    </th>
                    <th class="border p-2 text-center">A totales</th>
                    <th class="border p-2 text-center">I totales</th>
                    <th class="border p-2 text-center">IJ totales</th>
                  </tr>
                </thead>

                <tbody>
                  <tr v-if="asistenciasExcel.length === 0">
                    <td colspan="meses.lenght + 5" class="p-4 text-center text-gray-500">
                      No hay asistencias registradas en {{ anioSeleccionado }}
                    </td>
                  </tr>

                  <tr v-for="fila in asistenciasExcel" :key="fila.id" class="border-t border-gray-200 dark:border-gray-700">
                    <td class="border p-2 font-medium">{{ fila.integrante }}</td>
                    <td class="border p-2">{{ fila.puesto }}</td>
                    <td v-for="mes in meses" :key="mes" class="border p-2 text-center whitespace-nowrap">
                      {{ fila.meses[mes].length ? fila.mes[mes].join(', ') : '-' }}
                    </td>
                    <td class="border p-2 text-center">{{ fila.totalA }}</td>
                    <td class="border p-2 text-center">{{ fila.totalI }}</td>
                    <td class="border p-2 text-center">{{ fila.totalIJ }}</td>
                  </tr>
                </tbody>

                <tfoot class="bg-gray-100 font-semibold dark:bg-gray-500">
                  <tr>
                    <td colspan="2" class="border p-2">Total anual</td>
                    <td v-for="mes in meses" :key="mes" class="border p-2 text-center">
                      {{ totalesMensualesAnuales[mes] }}
                    </td>
                    <td class="border p-2 text-center">
                      {{ asistenciasExcel.reduce }}
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
           </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

