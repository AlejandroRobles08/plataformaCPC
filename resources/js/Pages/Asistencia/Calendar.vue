<script setup>
import { ref, computed, onMounted } from 'vue'
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
const sesionJustificante = ref(null)
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

// Color de cada tipo de sesión (el rojo queda reservado para faltas)
const coloresSesion = {
  ordinaria: 'yellow',
  solemne: '#4D634B',
  extraordinaria: '#782347'
}

// Equivalente hex de los colores de paleta de v-calendar (para el punto del popover)
const hexPaleta = {
  yellow: '#facc15',
  red: '#ef4444',
  gray: '#9ca3af'
}

// Simbología arriba del calendario; "Falta" solo para el integrante
const simbologia = computed(() => [
  { label: 'Ordinaria', clase: 'bg-yellow-400' },
  { label: 'Solemne', clase: 'bg-[#4D634B]' },
  { label: 'Extraordinaria', clase: 'bg-[#782347]'},
  ...(esIntegrante.value ? [{ label: 'Falta', clase: 'bg-red-500' }] : [])
])

// Texto según el estado del justificante de una falta
const textoJustificante = {
  pendiente: 'Justificante en revisión',
  rechazado: 'Justificante rechazado, puedes subir otro'
}

// Faltas del integrante (al aprobar el justificante pasan a 'justificada' y dejan de contar)
const faltas = computed(() => {
  if (!esIntegrante.value) return []
  return props.asistencias
    .filter(a => a.estado === 'falto')
    .sort((a, b) => a.fecha.localeCompare(b.fecha))
})

//si es que hay justificantes pendientes para revisar
const justificantesPendientes = computed(() => 
  faltas.value.some(a => a.estado_justificante !== 'pendiente')) 

const sesionesConFalta = computed(() =>
  new Set(faltas.value.map(a => Number(a.sesion_id)))
)

// Formato YYYY-MM-DD sin problemas de zona horaria
const formatearFechaLocal = date =>
  `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`

// Fecha legible en es-MX (ej. 1 de octubre de 2026)
const formatearFechaLegible = fecha =>
  new Date(`${fecha}T00:00:00`).toLocaleDateString('es-MX', {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })

// Justificantes aprobados que aún no se le han notificado al integrante (se guardan por navegador)
const justificantesAprobados = ref([])
const claveAprobados = `justificantes-aprobados-${props.consejo.id}-${props.integrante?.id}`

onMounted(() => {
  if (!esIntegrante.value || !props.integrante) return

  let notificados = []
  try {
    notificados = JSON.parse(localStorage.getItem(claveAprobados)) ?? []
  } catch {
    notificados = []
  }

  const nuevos = props.asistencias.filter(
    a => a.estado_justificante === 'aprobado' && !notificados.includes(a.id)
  )
  if (!nuevos.length) return

  justificantesAprobados.value = nuevos

  try {
    localStorage.setItem(claveAprobados, JSON.stringify([...notificados, ...nuevos.map(a => a.id)]))
  } catch {
    // Sin almacenamiento disponible la alerta se mostrará de nuevo en la próxima visita
  }

  // Ocultar la alerta después de 6 segundos
  setTimeout(() => {
    justificantesAprobados.value = []
  }, 6000)
})

// Click sobre una fecha del calendario
function abrirFormulario(day) {
  const fecha = formatearFechaLocal(day.date)

  if (esIntegrante.value) {
    abrirJustificante(fecha)
    return
  }

  if (!puedeCrearAsistencia.value) return
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

// Abrir justificante solo si el integrante tiene falta en esa fecha
function abrirJustificante(fecha) {
  if (!props.integrante) return

  // PENDIENTE: si el integrante faltó a dos sesiones el mismo día, por ahora se toma la primera falta de ese día
  const falta = faltas.value.find(a => a.fecha === fecha)
  if (!falta) return

  sesionJustificante.value = {
    fecha: falta.fecha,
    tipo_sesion: falta.tipo_sesion
  }
  showJustificante.value = true
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
  sesionJustificante.value = null
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

// Sesiones mostradas en el calendario (en rojo si el integrante tiene falta)
const calendarAttributes = computed(() =>
  props.sesiones.map(s => {
    const conFalta = sesionesConFalta.value.has(Number(s.id))
    const color = conFalta ? 'red' : (coloresSesion[s.tipo_sesion] || 'gray')

    // v-calendar solo reconoce nombres de su paleta; los colores hex se aplican con style
    const esHex = color.startsWith('#')
    const highlight = esHex
      ? { fillMode: 'solid', style: { backgroundColor: color }, contentStyle: { color: '#fff' } }
      : { fillMode: 'solid', color }
    const dot = esHex
      ? { style: { backgroundColor: color }, class: 'opacity-80' }
      : { color, class: 'opacity-80' }

    return {
      key: s.id,
      dates: new Date(`${s.fecha}T00:00:00`),
      highlight,
      dot,
      // El popover se dibuja en el slot day-popover para usar el mismo color de la sesión
      popover: { visibility: 'hover' },
      customData: {
        color: esHex ? color : (hexPaleta[color] || hexPaleta.gray),
        label: conFalta
          ? `Falta: sesión ${s.tipo_sesion} (${s.fecha})`
          : `Sesión ${s.tipo_sesion} (${s.fecha})`
      }
    }
  })
)
</script>

<template>
  <AuthenticatedLayout>
    <!-- Navegación -->
    <div class="mb-4 flex items-center justify-between gap-4">
      <!-- Volver -->
      <Link :href="route('asistencias.index', consejo.id)"
        class="inline-flex items-center px-3 py-2 bg-gray-200 text-gray-800 text-sm rounded hover:bg-gray-300 transition">
        ← Volver a Asistencias
      </Link>
    </div>

    <div v-if="mensajeExito" class="mb-4 rounded-lg border border-green-200 
    bg-green-100 px-4 py-3 text-green-800">
      {{ mensajeExito }} 
    </div>

    <!-- Alerta de justificantes aprobados (se oculta a los 6 segundos) -->
    <div v-if="justificantesAprobados.length"
      class="mb-4 rounded-lg border border-green-200 bg-green-100 px-4 py-3 text-green-800">
      <ul class="space-y-1">
        <li v-for="aprobado in justificantesAprobados" :key="aprobado.id">
          Tu justificante de la sesión {{ aprobado.tipo_sesion }} del día {{ formatearFechaLegible(aprobado.fecha) }} fue aprobado.
        </li>
      </ul>
    </div>

    <!-- Aviso de faltas del integrante -->
    <div v-if="esIntegrante && faltas.length"
      class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">
      <ul class="space-y-1">
        <li v-for="falta in faltas" :key="falta.id">
          Tienes una inasistencia a  la sesión {{ falta.tipo_sesion }} el día {{ formatearFechaLegible(falta.fecha) }}
          <span v-if="textoJustificante[falta.estado_justificante]" class="font-semibold">
            — {{ textoJustificante[falta.estado_justificante] }}
          </span>
        </li>
      </ul>
      <!--Mensaje se muestra solo si hay justificantes en revisión-->
      <p class="mt-2 text-sm">
        Si tienes un justificante, puedes subirlo <strong>dando clic en el calendario sobre la fecha de inasistencia
          para adjuntarlo y enviarlo</strong>
      </p>
      <p class="mt-2 text-sm"><strong>IMPORTANTE:</strong> El documento deberá ser aprobado por el administrador   </p>
    </div>

    <!-- Calendario -->
    <div class="p-4 md:p-6 w-full">
      <div class="w-full max-w-7xl mx-auto bg-white rounded-lg shadow p-4 md:p-6">
        <!-- Simbología -->
        <div v-if="puedeCrearAsistencia || esIntegrante" class="mb-4 flex flex-wrap items-center gap-4 text-sm text-gray-700">
          <div v-for="item in simbologia" :key="item.label" class="flex items-center gap-2">
            <span class="w-4 h-4 rounded-full" :class="item.clase"></span>
            {{ item.label }}
          </div>
        </div>

        <Calendar v-model="selectedDate" :attributes="calendarAttributes"
          expanded
          @dayclick="abrirFormulario"
          class="big-calendar">
          <!-- Popover con el punto del mismo color que la sesión -->
          <template #day-popover="{ dayTitle, attributes }">
            <div class="px-1 text-xs">
              <div class="mb-1 text-center font-semibold">
                {{ dayTitle }}
              </div>

              <div v-for="attr in attributes" :key="attr.key" class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: attr.customData.color }"></span>
                {{ attr.customData.label }}
              </div>
            </div>
          </template>
        </Calendar>
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
    <Justificante v-if="showJustificante && integrante && sesionJustificante" :consejo="consejo"
      :integrante="integrante"
      :sesion="sesionJustificante"
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