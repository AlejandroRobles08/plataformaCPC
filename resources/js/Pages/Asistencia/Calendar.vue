<script setup>
import { ref, computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Calendar } from 'v-calendar'
import 'v-calendar/dist/style.css'
import ProgramarSesion from './ProgramarSesion.vue'
import Form from './Form.vue'
import Justificante from './Justificantes/Integrante.vue'

const props = defineProps({
  sesiones: { type: Array, default: () => [] },
  consejo: Object,
  integrantes: { type: Array, default: () => [] },
  integrante: { type: Object, default: null },
  asistencias: { type: Array, default: () => [] }
})

const page = usePage()
const mensajeExito = ref('')
const showProgramarSesion = ref(false)
const showForm = ref(false)
const showJustificante = ref(false)
const fechaSeleccionada = ref(null)
const sesionSeleccionada = ref(null)
const selectedDate = ref(null)
const asistenciasSesion = ref([])

// Admin y super_admin pueden crear y registrar asistencias
const puedeCrearAsistencia = computed(() => {
  const roles = page.props.auth.roles ?? []
  return roles.includes('admin') || roles.includes('super_admin')
})

// Mostrar justificante únicamente a integrantes
const esIntegrante = computed(() =>
  page.props.auth?.roles?.includes('integrante') ?? false
)

// Formato YYYY-MM-DD sin problemas de zona horaria
const formatearFechaLocal = date =>
  `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`

// Click sobre una fecha del calendario
function abrirFormulario(day) {
  if (!puedeCrearAsistencia.value) return
  const fecha = formatearFechaLocal(day.date)
  const sesion = props.sesiones.find(s => s.fecha === fecha)
  fechaSeleccionada.value = fecha
  selectedDate.value = fecha

  // Registrar asistencia si la sesión ya existe
  if (sesion) {
    sesionSeleccionada.value = sesion
    asistenciasSesion.value = props.asistencias.filter(a => Number(a.sesion_id) === Number(sesion.id)
     )
    showForm.value = true
    return
  }
  // Programar una nueva sesión
  showProgramarSesion.value = true
}

// Cerrar modal de programación
function cerrarProgramacion() {
  showProgramarSesion.value = false
  fechaSeleccionada.value = null
}

// Cerrar modal de asistencia
function cerrarForm() {
  showForm.value = false
  sesionSeleccionada.value = null
  asistenciasSesion.value = []
}

// Cerrar modal de justificante
function cerrarJustificante() {
  showJustificante.value = false
}

function justificanteGuardado() {
  cerrarJustificante()
  mensajeExito.value = 'Justificante subido correctamente.'
  setTimeout(() => {
    mensajeExito.value = ''
  }, 4000)
}

// Recargar sesiones después de programar
function handleSesionProgramada() {
  cerrarProgramacion()
  router.reload({ only: ['sesiones'], preserveScroll: true })
}

// Recargar después de registrar asistencia
function handleAsistenciaGuardada() {
  cerrarForm()
  router.reload({ only: ['sesiones'], preserveScroll: true })
}

// Sesiones mostradas en el calendario
const calendarAttributes = computed(() =>
  props.sesiones.map(s => {
    const color = {
      ordinaria: 'yellow',
      solemne: 'green',
      extraordinaria: 'red'
    }[s.tipo_sesion] || 'gray'

    return {
      key: s.id,
      dates: new Date(`${s.fecha}T00:00:00`),
      highlight: { fillMode: 'solid', color },
      dot: { color, class: 'opacity-80' },
      popover: {
        label: `Sesión ${s.tipo_sesion} (${s.fecha})`
      }
    }
  })
)
</script>

<template>
  <AuthenticatedLayout>
    <!-- Navegación y acciones -->
    <div class="mb-4 flex items-center justify-between gap-4">
      <!-- Volver -->
      <Link :href="route('asistencias.index', consejo.id)"
        class="inline-flex items-center px-3 py-2 bg-gray-200 text-gray-800 text-sm rounded hover:bg-gray-300 transition">
        ← Volver a Asistencias
      </Link>

      <!-- Justificante para integrante -->
      <button v-if="esIntegrante && integrante" type="button"
        class="px-4 py-2 bg-yellow-700 text-white rounded hover:bg-yellow-800 transition"
        @click="showJustificante = true">
        Subir justificante
      </button>
    </div>

    <div v-if="mensajeExito" class="mb-4 rounden-lg border border-green-200 
    bg-green-100 px-4 py-3 text-green-800">
      {{ mensajeExito }} 
    </div>

    <!-- Calendario -->
    <div class="p-4 md:p-6 w-full">
      <div class="w-full max-w-7xl mx-auto bg-white rounded-lg shadow p-4 md:p-6">
        <Calendar v-model="selectedDate" :attributes="calendarAttributes"
          @dayclick="abrirFormulario"
          class="big-calendar"/>
      </div>
    </div>

    <!-- Programar sesión -->
    <ProgramarSesion v-if="showProgramarSesion" :consejo-id="consejo.id"
      :fecha="fechaSeleccionada"
      @close="cerrarProgramacion"
      @saved="handleSesionProgramada"/>

    <!-- Registrar asistencia -->
    <Form v-if="showForm && sesionSeleccionada" :sesion="sesionSeleccionada" :integrantes="integrantes"
      :asistencias="asistenciasSesion"
      :consejo-id="consejo.id"
      @close="cerrarForm"
      @saved="handleAsistenciaGuardada"/>

    <!-- Subir justificante -->
    <Justificante v-if="showJustificante && integrante" :consejo="consejo"
      :integrante="integrante"
      @close="cerrarJustificante"
      @saved="justificanteGuardado"/>
  </AuthenticatedLayout>
</template>

<style scoped>
/* Contenedor principal */
.big-calendar {
  width: 100% !important;
  max-width: 100% !important;
  font-size: 1.35rem;
}

/* Calendario interno */
:deep(.vc-container) {
  width: 100% !important;
  max-width: none !important;
}

/* Panel interno */
:deep(.vc-pane-layout),
:deep(.vc-pane) {
  width: 100% !important;
}

/* Grid */
:deep(.vc-weeks) {
  width: 100% !important;
}

/* Siete columnas proporcionales */
:deep(.vc-weekday),
:deep(.vc-day) {
  width: calc(100% / 7) !important;
}

/* Área de cada día */
:deep(.vc-day-content) {
  width: 100% !important;
  height: clamp(3rem, 6vw, 5rem) !important;
  font-size: clamp(1rem, 1.5vw, 1.3rem) !important;
}

/* Hover */
:deep(.vc-day:hover > .vc-day-content) {
  background: #e5e7eb !important;
  border-radius: 10px;
  transition: .15s ease;
}

/* Título */
:deep(.vc-title) {
  font-size: clamp(1.3rem, 2vw, 1.8rem) !important;
  font-weight: 600 !important;
}

/* Flechas */
:deep(.vc-arrow) {
  transform: scale(1.3);
}
</style>