<script setup>
import { ref, onMounted, watch } from 'vue'
import { Chart, registerables } from 'chart.js'
import { wrapLabel } from './wrapLabel'
Chart.register(...registerables)

const props = defineProps({
  labels: Array,
  data: Array,
  title: String,
})

const chartCanvas = ref(null)
let chartInstance = null

const renderChart = () => {
  if (chartInstance) chartInstance.destroy()

  chartInstance = new Chart(chartCanvas.value, {
    type: 'bar',
    data: {
      labels: (props.labels || []).map((l) => wrapLabel(l)),
      datasets: [
        {
          label: props.title,
          data: props.data,
          backgroundColor: 'rgba(111, 0, 26, 0.6)',  // guinda
          borderColor: 'rgb(111, 0, 26)',
          borderWidth: 1,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,   // 🎯 CLAVE PARA QUE LA GRÁFICA QUEPA EN SU DIV
      indexAxis: 'y',               // barras horizontales para que los nombres largos se lean completos
      scales: {
        x: {
          beginAtZero: true,
          max: 30,
          ticks: {stepSize:5, precision: 0 }
        },
        y: {
          ticks: { autoSkip: false }  // mostrar todos los consejos
        },
      }
    }
  })
}

onMounted(renderChart)
watch(() => props.data, renderChart)
</script>

<template>
  <!-- Alto explícito para que Chart.js se adapte -->
  <div class="bg-white p-4 rounded-lg shadow h-full">
    <canvas ref="chartCanvas"></canvas>
  </div>
</template>
