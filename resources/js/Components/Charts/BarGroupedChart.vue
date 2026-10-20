<script setup>
import { ref, onMounted, watch } from 'vue'
import { Chart, registerables } from 'chart.js'
import { wrapLabel } from './wrapLabel'
Chart.register(...registerables)

const props = defineProps({
  labels: { type: Array, default: () => [] },
  datasets: { type: Object, default: () => ({}) },
})

const generoOrder = [
  'Mujer',
  'Hombre',
  'Otro',
  'Prefiero no responder'
]

const colorByGender = {
  'Mujer': '#7B1E3A',
  'Hombre': '#6E6E6E',
  'Otro': '#C9A227',
  'Prefiero no responder': '#3F6B4C'
}

const chartCanvas = ref(null)
let chartInstance = null

function buildChartData() {
  const labels = props.labels || []
  return {
    labels: labels.map((l) => wrapLabel(l)),
    datasets: generoOrder.map((gen) => {
      const raw = Array.isArray(props.datasets?.[gen]) ? props.datasets[gen] : []
      const data = labels.map((_, i) => raw[i] ?? 0)
      return {
        label: gen,
        data,
        backgroundColor: colorByGender[gen] || '#999',
        borderColor: colorByGender[gen] || '#999',
        borderWidth: 0,
        borderRadius: 6,
      }
    })
  }
}

const options = {
  responsive: true,
  maintainAspectRatio: false,  // 🎯 CLAVE PARA QUE NO SE APLASTE
  indexAxis: 'y',  // barras horizontales para que los nombres largos se lean completos
  plugins: {
    legend: { position: 'top' },
  },
  scales: {
    x: {
      beginAtZero: true,
      ticks: {
        stepSize: 1,
        precision: 0,
      }
    },
    y: {
      ticks: { autoSkip: false }  // mostrar todos los consejos
    }
  }
}

function renderChart() {
  if (!chartCanvas.value) return
  if (chartInstance) chartInstance.destroy()
  chartInstance = new Chart(chartCanvas.value, {
    type: 'bar',
    data: buildChartData(),
    options,
  })
}

onMounted(renderChart)
watch(() => [props.labels, props.datasets], renderChart, { deep: true })
</script>

<template>
  <div class="bg-white p-4 rounded-lg shadow h-full">
    <canvas ref="chartCanvas"></canvas>
  </div>
</template>
