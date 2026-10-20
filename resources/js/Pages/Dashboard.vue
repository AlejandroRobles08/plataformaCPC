<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import BarChart from '@/Components/Charts/BarChart.vue'
import BarGroupedChart from '@/Components/Charts/BarGroupedChart.vue'
import PieChart from '@/Components/Charts/PieChart.vue';

const props = defineProps({
  labels: Array,
  data: Array,
  generoLabels: Array,
  generoData: Object,
  generoTotales: Object,
  discapacidadTotales: Object
})

const page = usePage()

const esInvitado = computed(() =>
  page.props.auth?.roles?.includes('invitado')
)

const generoOrder = [
  'Mujer',
  'Hombre',
  'Otro',
  'Prefiero no responder'
];

const colorByGenero = {
  'Mujer': '#7B1E3A',
  'Hombre': '#6E6E6E',
  'Otro': '#C9A227',
  'Prefiero no responder': '#3F6B4C'

};

const discapacidadLabels = ['Con discapacidad', 'Sin discapacidad']

const discapacidadData = discapacidadLabels.map(d => props.discapacidadTotales?.[d] ?? 0)
const discapacidadColors = [
  '#AF1731',
  '#246257'
]

const pieLabels = generoOrder
const pieData = generoOrder.map(g => props.generoTotales?.[g] ?? 0)
const pieColors = generoOrder.map(g => colorByGenero[g])
</script>


<template>
  <AuthenticatedLayout>
    <Head title="Dashboard" />

     <div class="bg-white rounded-xl shadow-md p-6">
     <div class="flex items-center gap-6">
        <img
            src="/images/logo-implan.png" class="w-60 h-auto object-contain">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">
                Instituto Municipal de Planeación de Puebla
            </h1>
            <p v-if="!esInvitado" class="mt-5 text-gray-500">
                Tablero de estadísticas
            </p>
         </div>
       </div>
     </div>

    <div v-if="!esInvitado" class="px-6 py-8 space-y-10">

      <!-- === Gráficoa de pastel 2 === -->
      <div class="bg-white border rounded-xl shadow p-6">
        <h2 class="text-xl font-bold mb-6">Integrantes por Consejo</h2>

        <div class="h-[560px]">
          <BarChart :labels="props.labels" :data="props.data" title="Total de Integrantes por Consejo" />
        </div>
      </div>

      <!-- Grafica 2 -->
      <div class="bg-white border rounded-xl shadow p-6">
        <h2 class="text-xl font-bold mb-6">Integrantes por Género y Consejo</h2>

        <div class="h-[760px]">
          <BarGroupedChart :labels="props.labels" :datasets="props.generoData" />
        </div>
      </div>

      <!-- Gracfica 3-->
      <div class="bg-white border rounded-xl shadow p-6">
        <h2 class="text-xl font-bold mb-6">Distribución por Género</h2>
        <div class="h-[350px]">
          <PieChart :labels="pieLabels" :data="pieData" :colors="pieColors"
            title="Distribución de Integrantes por Género" />
        </div>
      </div>
      
      <!-- grafica 4-->
       <div class="bg-white border rounded-xl shadow p-6">
        <h2 class ="text-xl font-bold mb-6">Distribución por Discapacidad</h2>
        <div class="h-[350px]">
          <PieChart
          :labels="discapacidadLabels"
          :data="discapacidadData"
          :colors="discapacidadColors"
          title="Discapacidad"/>
        </div>
       </div>

    </div>
  </AuthenticatedLayout>
</template>
