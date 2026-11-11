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
  integrantesSinPaseLista: { type: Array, default: () => [] },
  // Ids sin todos sus documentos del Archivo digital aprobados
  integrantesSinExpediente: { type: Array, default: () => [] },
  integrante: { type: Object, default: null },
  asistencias: { type: Array, default: () => [] },
  maxSesionesPorDia: { type: Number, default: 2 }
})

const page = usePage()
const mensajeExito = ref('')
const showProgramarSesion = ref(false)
const showForm = ref(false)
const showJustificante = ref(false)
const fechaSeleccionada = ref(null)
const sesionSeleccionada = ref(null)
const sesionJustificante = ref(null)
const asistenciasSesion = ref([])
// true cuando se programa una sesión adicional desde el formulario de asistencia
const programacionAdicional = ref(false)

// Sesiones programadas en una fecha (ordenadas por creación: sesión 1, sesión 2)
const sesionesDeFecha = fecha =>
  props.sesiones
    .filter(s => s.fecha === fecha)
    .sort((a, b) => Number(a.id) - Number(b.id))

const sesionesDiaSeleccionado = computed(() =>
  fechaSeleccionada.value ? sesionesDeFecha(fechaSeleccionada.value) : []
)

// Se puede agregar otra sesión mientras no se alcance el máximo por día
const puedeAgregarSesion = computed(() =>
  sesionesDiaSeleccionado.value.length > 0 &&
  sesionesDiaSeleccionado.value.length < props.maxSesionesPorDia
)

// Tipos ya usados ese día (no se puede repetir el tipo en la misma fecha)
const tiposOcupadosDia = computed(() =>
  sesionesDiaSeleccionado.value.map(s => s.tipo_sesion)
)

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
const hexPaleta = { yellow: '#facc15', red: '#ef4444', gray: '#9ca3af' }

// Simbología arriba del calendario; "Falta" solo para el integrante
const simbologia = computed(() => [
  { label: 'Ordinaria', clase: 'bg-yellow-400' },
  { label: 'Solemne', clase: 'bg-[#4D634B]' },
  { label: 'Extraordinaria', clase: 'bg-[#782347]'},
  ...(esIntegrante.value ? [{ label: 'Falta', clase: 'bg-red-500' }] : [])
])

// Texto según el estado del justificante de una falta
const textoJustificante = { rechazado: 'Justificante rechazado, puedes subir otro' }

// Faltas del integrante (al aprobar el justificante pasan a 'justificada' y dejan de contar)
const faltas = computed(() => {
  if (!esIntegrante.value) return []
  return props.asistencias
    .filter(a => a.estado === 'falto')
    .sort((a, b) => a.fecha.localeCompare(b.fecha))
})

// Faltas con justificante en validación: se avisan aparte en el recuadro amarillo
const justificantesEnValidacion = computed(() =>
  faltas.value.filter(a => a.estado_justificante === 'pendiente')
)

// Faltas que el integrante todavía puede justificar: se avisan en el recuadro rojo
const faltasPorJustificar = computed(() =>
  faltas.value.filter(a => a.estado_justificante !== 'pendiente')
)

// Dos sesiones el mismo día, con un justificante enviado y otro no: los avisos van lado a lado
const avisosLadoALado = computed(() => {
  const fechasEnValidacion = new Set(justificantesEnValidacion.value.map(a => a.fecha))
  return faltasPorJustificar.value.some(a => fechasEnValidacion.has(a.fecha))
})

const sesionesConFalta = computed(() =>
  new Set(faltas.value.map(a => Number(a.sesion_id)))
)

// Mes que se muestra al abrir el calendario (fecha local del navegador)
const hoy = new Date()
const mesActual = { month: hoy.getMonth() + 1, year: hoy.getFullYear() }

// Formato YYYY-MM-DD sin problemas de zona horaria
const formatearFechaLocal = date =>
  `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`

// Fecha legible en es-MX (ej. 1 de octubre de 2026)
const formatearFechaLegible = fecha =>
  new Date(`${fecha}T00:00:00`).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' })

// Justificantes aprobados que aún no se le han notificado al integrante (se guardan por navegador)
const justificantesAprobados = ref([])
const claveAprobados = `justificantes-aprobados-${props.consejo.id}-${props.integrante?.id}`

onMounted(() => {
  if (!esIntegrante.value || !props.integrante) return

  let notificados = []
  try { notificados = JSON.parse(localStorage.getItem(claveAprobados)) ?? [] } catch { notificados = [] }

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
  setTimeout(() => { justificantesAprobados.value = [] }, 6000)
})

// Click sobre una fecha del calendario
function abrirFormulario(day) {
  const fecha = formatearFechaLocal(day.date)

  if (esIntegrante.value) {
    abrirJustificante(fecha)
    return
  }

  if (!puedeCrearAsistencia.value) return
  fechaSeleccionada.value = fecha
  programacionAdicional.value = false

  // Registrar asistencia si ya hay sesión ese día (se abre la primera; el formulario permite cambiar)
  const [primeraSesion] = sesionesDeFecha(fecha)
  if (primeraSesion) {
    abrirFormSesion(primeraSesion)
    return
  }
  // Programar una nueva sesión
  showProgramarSesion.value = true
}

// Abre el pase de lista de una sesión específica
function abrirFormSesion(sesion) {
  sesionSeleccionada.value = sesion
  asistenciasSesion.value = props.asistencias.filter(
    a => Number(a.sesion_id) === Number(sesion.id)
  )
  showForm.value = true
}

// Botón "Otra sesión este día": se cierra el pase de lista y se programa otra sesión en la misma fecha
function programarSesionAdicional() {
  showForm.value = false
  programacionAdicional.value = true
  showProgramarSesion.value = true
}

// Abrir justificante solo si el integrante tiene falta en esa fecha
function abrirJustificante(fecha) {
  if (!props.integrante) return

  // Si faltó a dos sesiones el mismo día, se prioriza la falta que aún no tiene justificante en revisión
  const faltasDia = faltas.value.filter(a => a.fecha === fecha)
  const falta = faltasDia.find(a => a.estado_justificante !== 'pendiente') ?? faltasDia[0]
  if (!falta) return

  sesionJustificante.value = {
    fecha: falta.fecha,
    tipo_sesion: falta.tipo_sesion,
    asistencia_id: falta.id,
    estado_justificante: falta.estado_justificante
  }
  showJustificante.value = true
}

// Cerrar modal de programación
function cerrarProgramacion() {
  // ProgramarSesion emite 'close' después de 'saved'; si ya se cerró no hay nada que hacer
  if (!showProgramarSesion.value) return
  showProgramarSesion.value = false

  // Si se canceló la sesión adicional se regresa al pase de lista de la sesión que estaba abierta
  if (programacionAdicional.value && sesionSeleccionada.value) {
    programacionAdicional.value = false
    abrirFormSesion(sesionSeleccionada.value)
    return
  }

  programacionAdicional.value = false
  fechaSeleccionada.value = null
}

// Cerrar modal de asistencia
function cerrarForm() {
  showForm.value = false
  sesionSeleccionada.value = null
  asistenciasSesion.value = []
  fechaSeleccionada.value = null
}

// Cerrar modal de justificante
function cerrarJustificante() {
  showJustificante.value = false
  sesionJustificante.value = null
}

function justificanteGuardado() {
  cerrarJustificante()
  mensajeExito.value = 'Justificante subido correctamente.'
  setTimeout(() => { mensajeExito.value = '' }, 4000)
}

// Recargar sesiones después de programar
function handleSesionProgramada() {
  const esAdicional = programacionAdicional.value
  const fecha = fechaSeleccionada.value
  const idsPrevios = new Set(sesionesDeFecha(fecha).map(s => Number(s.id)))

  showProgramarSesion.value = false
  programacionAdicional.value = false

  router.reload({
    only: ['sesiones'],
    preserveScroll: true,
    onSuccess: () => {
      if (!esAdicional) {
        fechaSeleccionada.value = null
        return
      }
      // Se abre directamente el pase de lista de la sesión recién programada
      const nueva = sesionesDeFecha(fecha).find(s => !idsPrevios.has(Number(s.id)))
      if (nueva) abrirFormSesion(nueva)
    }
  })
}

// Recargar después de registrar asistencia
function handleAsistenciaGuardada() {
  cerrarForm()
  router.reload({ only: ['sesiones'], preserveScroll: true })
}

// Horario 'HH:MM – HH:MM' de una sesión (las anteriores al cambio no lo tienen)
const horarioSesion = s => s.hora_inicio && s.hora_fin
  ? ` · ${s.hora_inicio.slice(0, 5)} – ${s.hora_fin.slice(0, 5)}` : ''

// Sesiones mostradas en el calendario (en rojo si el integrante tiene falta)
// Si hay dos sesiones el mismo día, el día se resalta con la primera y cada sesión agrega su punto
const calendarAttributes = computed(() => {
  const fechasResaltadas = new Set()

  return [...props.sesiones]
    .sort((a, b) => a.fecha.localeCompare(b.fecha) || Number(a.id) - Number(b.id))
    .map(s => {
    const esPrimeraDelDia = !fechasResaltadas.has(s.fecha)
    fechasResaltadas.add(s.fecha)
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
      // Solo la primera sesión del día resalta el fondo para que no se encimen los colores
      ...(esPrimeraDelDia ? { highlight } : {}),
      dot,
      // El popover se dibuja en el slot day-popover para usar el mismo color de la sesión
      popover: { visibility: 'hover' },
      customData: {
        color: esHex ? color : (hexPaleta[color] || hexPaleta.gray),
        label: conFalta
          ? `Falta: sesión ${s.tipo_sesion} (${s.fecha})${horarioSesion(s)}`
          : `Sesión ${s.tipo_sesion} (${s.fecha})${horarioSesion(s)}`
      }
    }
  })
})
</script>

<template>
  <AuthenticatedLayout>
    <!-- Volver -->
    <Link :href="route('asistencias.index', consejo.id)" class="mb-4 inline-flex items-center px-3 py-2 bg-gray-200 text-gray-800 text-sm rounded hover:bg-gray-300 transition">← Volver a Asistencias</Link>

    <!-- Instrucciones para administrador -->
    <p v-if="puedeCrearAsistencia" class="mb-6 text-lg text-gray-500">Para programar una sesión, da clic sobre la fecha deseada en el calendario. Para registrar la asistencia de una sesión ya programada, da clic sobre la misma fecha de la sesión.</p>

    <!-- Instrucciones para integrante -->
    <p v-if="esIntegrante" class="mb-6 text-lg text-gray-500">
      Si usted no pudo asistir a una sesión pero cuenta con un justificante, puede subirlo dando clic sobre la fecha de la sesión en el calendario.
      <br><strong>IMPORTANTE:</strong> El sistema le notificará cuando no haya asistido a una sesión y cuando su justificante haya sido aprobado o rechazado.
    </p>

    <div v-if="mensajeExito" class="mb-4 rounded-lg border border-green-200 bg-green-100 px-4 py-3 text-green-800">{{ mensajeExito }}</div>

    <!-- Alerta de justificantes aprobados (se oculta a los 6 segundos) -->
    <ul v-if="justificantesAprobados.length" class="mb-4 space-y-1 rounded-lg border border-green-200 bg-green-100 px-4 py-3 text-green-800">
      <li v-for="aprobado in justificantesAprobados" :key="aprobado.id">Tu justificante de la sesión {{ aprobado.tipo_sesion }} del día {{ formatearFechaLegible(aprobado.fecha) }} fue aprobado.</li>
    </ul>

    <!-- Avisos del integrante: primero las faltas (rojo) y después los justificantes en validación (amarillo) -->
    <div v-if="esIntegrante && faltas.length" class="mb-4 grid grid-cols-1 gap-4" :class="{ 'md:grid-cols-2': avisosLadoALado }">
      <!-- Aviso de faltas del integrante -->
      <div v-if="faltasPorJustificar.length" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">
        <ul class="space-y-1">
          <li v-for="falta in faltasPorJustificar" :key="falta.id">
            Tienes una inasistencia a la <strong>sesión {{ falta.tipo_sesion }}</strong> el día {{ formatearFechaLegible(falta.fecha) }}
            <span v-if="textoJustificante[falta.estado_justificante]" class="font-semibold">— {{ textoJustificante[falta.estado_justificante] }}</span>
          </li>
        </ul>
        <p class="mt-2 text-sm">Si tienes un justificante, puedes subirlo <strong>dando clic en el calendario sobre la fecha de inasistencia para adjuntarlo y enviarlo.</strong></p>
        <p class="mt-2 text-sm"><strong>IMPORTANTE:</strong> El documento deberá ser aprobado por el administrador.</p>
      </div>

      <!-- Aviso de justificantes en validación -->
      <div v-if="justificantesEnValidacion.length" class="rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-yellow-800">
        <ul class="space-y-1">
          <li v-for="falta in justificantesEnValidacion" :key="falta.id">Tu justificante de la <strong>sesión {{ falta.tipo_sesion }}</strong> del día {{ formatearFechaLegible(falta.fecha) }} está en validación.</li>
        </ul>
        <p class="mt-2 text-sm">Se te notificará cuando el administrador lo apruebe o lo rechace.</p>
      </div>
    </div>

    <!-- Calendario -->
    <div class="p-4 md:p-6 w-full">
      <div class="w-full max-w-7xl mx-auto bg-white rounded-lg shadow p-4 md:p-6">
        <!-- Simbología -->
        <div v-if="puedeCrearAsistencia || esIntegrante" class="mb-4 flex flex-wrap items-center gap-4 text-sm text-gray-700">
          <span v-for="item in simbologia" :key="item.label" class="flex items-center gap-2"><span class="w-4 h-4 rounded-full" :class="item.clase"></span>{{ item.label }}</span>
        </div>

        <!-- initial-page: abre en el mes actual (sin él, v-calendar abre en el mes de la primera sesión) -->
        <Calendar :attributes="calendarAttributes" :initial-page="mesActual" expanded class="big-calendar" @dayclick="abrirFormulario">
          <!-- Popover con el punto del mismo color que la sesión -->
          <template #day-popover="{ dayTitle, attributes }">
            <div class="px-1 text-xs">
              <div class="mb-1 text-center font-semibold">{{ dayTitle }}</div>
              <div v-for="attr in attributes" :key="attr.key" class="flex items-center gap-2"><span class="w-3 h-3 rounded-full" :style="{ backgroundColor: attr.customData.color }"></span>{{ attr.customData.label }}</div>
            </div>
          </template>
        </Calendar>
      </div>
    </div>

    <!-- Programar sesión -->
    <ProgramarSesion v-if="showProgramarSesion" :consejo-id="consejo.id" :fecha="fechaSeleccionada" :adicional="programacionAdicional"
      :tipos-ocupados="programacionAdicional ? tiposOcupadosDia : []" @close="cerrarProgramacion" @saved="handleSesionProgramada"/>

    <!-- Registrar asistencia (key: se reinicia el formulario al cambiar de sesión) -->
    <Form v-if="showForm && sesionSeleccionada" :key="sesionSeleccionada.id" :sesion="sesionSeleccionada" :integrantes="integrantes"
      :integrantes-sin-pase-lista="integrantesSinPaseLista" :integrantes-sin-expediente="integrantesSinExpediente" :asistencias="asistenciasSesion"
      :consejo-id="consejo.id" :sesiones-dia="sesionesDiaSeleccionado" :puede-agregar-sesion="puedeAgregarSesion"
      @close="cerrarForm" @saved="handleAsistenciaGuardada" @cambiar-sesion="abrirFormSesion" @nueva-sesion="programarSesionAdicional"/>

    <!-- Subir justificante -->
    <Justificante v-if="showJustificante && integrante && sesionJustificante" :consejo="consejo" :integrante="integrante" :sesion="sesionJustificante"
      @close="cerrarJustificante" @saved="justificanteGuardado"/>
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

/* Panel interno y grid */
:deep(.vc-pane-layout),
:deep(.vc-pane),
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