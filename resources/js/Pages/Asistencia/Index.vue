<script setup>
import SemaforoAsistencias from '@/Components/SemaforoAsistencias.vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import {ClipboardDocumentIcon,CalendarIcon,FlagIcon,DocumentCheckIcon} from '@heroicons/vue/24/solid'
import { ref, computed } from 'vue'
import Swal from 'sweetalert2'
import Form from './Form.vue'
import HistoryModal from './History.vue'
import JustificantesAdmin from './Justificantes/Admin.vue'

const page = usePage()
const esIntegrante = computed(() =>
  page.props.auth.roles?.includes('integrante') ?? false
)
const esAdministrador = computed(() => {
  const roles = page.props.auth.roles ?? []
  return roles.includes('admin') || roles.includes('super_admin')
})
const esMiIntegrante = (integrante) => {
  if(esAdministrador.value) {return true}
  return integrante?.id === props.integranteActualId
}
const props = defineProps({
  consejo: Object,
  integrantes: Array,
  asistencias: Array,
  integranteActualId: {
    type: Number,
    default: null
  },
  justificantes: {
    type: Array,
    default: () => []
  },
  // Color del semáforo por integrante (la regla se calcula en el backend)
  semaforos: {
    type: [Object, Array],
    default: () => ({})
  }
})

const showModal = ref(false)
const showHistorial = ref(false)
const showJustificantes = ref(false)

const integranteSeleccionado = ref(null)
const historialSeleccionado = ref([])

function abrirModal() {
  showModal.value = true
}

function cerrarModal() {
  showModal.value = false
}

function abrirHistorial(integrante) {
  integranteSeleccionado.value = integrante

  historialSeleccionado.value = props.asistencias.filter(
    a => a.integrante_id === integrante.id
  )

  showHistorial.value = true

  // Si el integrante abre su propio historial con comentarios sin ver, se marcan como vistos
  if (badgeRechazados(integrante)) {
    router.patch(
      route('justificantes.comentarios.vistos', props.consejo.id),
      {},
      { preserveScroll: true, preserveState: true, only: ['asistencias'] }
    )
  }
}

function cerrarHistorial() {
  showHistorial.value = false
  integranteSeleccionado.value = null
  historialSeleccionado.value = []
}

function abrirJustificantes() {
  showJustificantes.value = true
}

function cerrarJustificantes() {
  showJustificantes.value = false
}

const justificantesPendientes = computed(() =>
  props.justificantes.filter(
    j => j.estado_justificante === 'pendiente'
  ).length
)

// Cuenta los justificantes rechazados cuyo comentario el integrante no ha visto (badge del botón Historial)
function justificantesRechazados(integranteId) {
  return props.asistencias.filter(
    a => a.integrante_id === integranteId &&
      a.estado_justificante === 'rechazado' &&
      !a.comentario_visto_at
  ).length
}

// Valor de la burbuja: solo visible para el propio integrante (es quien puede marcar sus comentarios como vistos)
function badgeRechazados(integrante) {
  if (!esIntegrante.value || integrante.correo !== page.props.auth.user?.email) return 0
  return justificantesRechazados(integrante.id)
}

// total de faltas del integrante (burbuja del boton ver calendario)
const totalFaltas = computed(() => {
  if (!esIntegrante.value) return 0
  return props.asistencias.filter(
    a => a.integrante_id === props.integranteActualId && a.estado === 'falto').length
})

const formulas = computed(() => {
  const grupos = {}
  props.integrantes.forEach(integrante => {
    const numeroFormula = integrante.formula
    if (!grupos[numeroFormula]) {
      grupos[numeroFormula] = []
    }
    grupos[numeroFormula].push(integrante)
  })
  return Object.entries(grupos).sort(([a],[b])=> Number(a) - Number(b))
  .map(([formula, integrantes]) => ({
    formula, integrantes
  }))
})

function obtenerSemaforo(integranteId) {
  return props.semaforos[integranteId]
}

// Al entrar al calendario, avisa al integrante en rojo; devolver false detiene la visita hasta cerrar la alerta
function avisarFaltasConsecutivas() {
  if (!esIntegrante.value || obtenerSemaforo(props.integranteActualId) !== 'rojo') return true

  Swal.fire({
    icon: 'warning',
    title: 'Ha acumulado 3 faltas sin justificar',
    text: 'Si no justifica sus inasistencias, causará baja del sistema. Puede adjuntar su justificante dando clic en el calendario sobre la fecha de la inasistencia. Si ya lo envió, espere a que sea validado.',
    confirmButtonText: 'Entendido',
    confirmButtonColor: '#374151'
  }).then(() => router.visit(route('asistencia.calendar', props.consejo.id)))

  return false
}

function colorClase(color) {
  return {
    verde: 'bg-green-500',
    amarillo: 'bg-yellow-400',
    rojo: 'bg-red-500'
  }[color] || 'bg-gray-300'
}
</script>

<template>
  <AuthenticatedLayout>
    <div class="mt-2 mb-4">
      <Link :href="route('consejos.asistencias', consejo.id)" class="text-gray-600 hover:underline">
        ← Volver a Consejos de Participación Ciudadana
      </Link>
    </div>

    <div class="p-6">
      <h1 class="text-2xl font-bold mb-2">
        Registro de asistencias del consejo: {{ props.consejo.nombre }}
      </h1>
      <div class="mb-6 grid grid-cols-1 gap-4 text-sm text-gray-600 md:grid-cols-2">
        <!-- Informativo de la semaforización -->
        <SemaforoAsistencias :es-integrante="esIntegrante" />

        <!-- Instrucciones -->
        <div class="space-y-2 rounded-lg border border-gray-200 p-4">
          <template v-if="esAdministrador">
            <p>
              En el botón <strong>Evidencia documental</strong>, estarán disponibles para visualización las listas de asistencia de cada sesión.
            </p>
            <p>
              En el botón <strong>Ver calendario</strong>, usted podrá programar las sesiones que se llevarán a cabo, así como pasar lista de asistencia.
            </p>
          </template>
          <p v-if="esIntegrante">
            En el botón <strong>Ver calendario</strong>, usted podrá visualizar sus asistencias e inasistencias, así como el total acumulado de las mismas.
            En caso de que haya faltado a alguna sesión, podrá adjuntar un justificante en formato PDF para su revisión.
          </p>
        </div>
      </div>

      <div class="flex gap-4 mb-6">
        <Link v-if="!esIntegrante" :href="route('asistencias.evidencias', consejo.id)"
          class="flex items-center gap-2 px-4 py-2 bg-red-700 text-white rounded hover:bg-red-900">
          <ClipboardDocumentIcon class="w-5 h-5" />
          Evidencia documental
        </Link>

        <Link :href="route('asistencia.calendar', props.consejo.id)" class="relative flex items-center gap-2
        px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-800" @before="avisarFaltasConsecutivas">
          <CalendarIcon class="w-5 h-5" />
          Ver calendario
          <span v-if="totalFaltas" class="absolute -top-2 -right-2 min-w-5 h-5 px-1 flex items-center justify-center rounded-full bg-red-600 text-xs font-bold">
            {{ totalFaltas }}
          </span>
        </Link>

        <button v-if="esAdministrador" @click="abrirJustificantes" class="relative flex items-center 
        gap-2 px-4 py-2 bg-yellow-700 text-white rounded hover:bg-yellow-800">
          <DocumentCheckIcon class="w-5 h-5" />
          Justificantes
          <span
            v-if="justificantesPendientes"
            class="absolute -top-2 -right-2 min-w-5 h-5 px-1 flex items-center justify-center rounded-full bg-red-600 text-xs font-bold">
            {{ justificantesPendientes }}
          </span>

        </button>
      </div>

      <div v-if="formulas.length" class="overflow-x-auto">
        <table class="min-w-full border border-gray-300 rounded-lg">
          <thead class="bg-gray-100">
            <tr>
              <th class="px-4 py-2 border"># Fórmula</th>
              <th class="px-4 py-2 border">Integrantes</th>
              <th class="px-4 py-2 border">Asistencias</th>
            </tr>
          </thead>

          <tbody>

            <tr v-for="(f, index) in formulas"
              :key="index" class="hover:bg-gray-50">
              <td class="px-4 py-2 border text-center font-semibold">
                {{ f.formula }}
              </td>

              <td class="px-4 py-2 border">
                <div v-if="f.integrantes[0]" class="flex items-center gap-2">
                  <span v-if="esMiIntegrante(f.integrantes[0])" class="w-3 h-3 rounded-full"
                    :class="colorClase(obtenerSemaforo(f.integrantes[0].id))">
                  </span>

                  {{ f.integrantes[0].nombre }} {{ f.integrantes[0].apellido }}
                </div>

                <div v-else class="text-gray-400">
                  Pendiente
                </div>

                <br/>

                <div v-if="f.integrantes[1]" class="flex items-center gap-2">
                  <span v-if="esMiIntegrante(f.integrantes[1])" class="w-3 h-3 rounded-full"
                    :class="colorClase(obtenerSemaforo(f.integrantes[1].id))">
                  </span>

                  {{ f.integrantes[1].nombre }} {{ f.integrantes[1].apellido }}
                </div>

                <div v-else class="text-gray-400">
                  Pendiente
                </div>
              </td>

              <td class="px-4 py-2 border text-sm">

                <div v-if="f.integrantes[0] && esMiIntegrante(f.integrantes[0])" class="mb-3">
                  <button @click="abrirHistorial(f.integrantes[0])"
                    class="relative px-3 py-1 bg-gray-600 text-white rounded hover:bg-gray-800 text-sm">
                    <FlagIcon class="w-4 h-4 inline-block mr-1" />
                    Historial

                    <span v-if="badgeRechazados(f.integrantes[0])"
                      class="absolute -top-2 -right-2 min-w-5 h-5 px-1 flex items-center justify-center 
                      rounded-full bg-red-600 text-xs font-bold">
                      {{ badgeRechazados(f.integrantes[0]) }}
                    </span>
                  </button>
                </div>

                <hr class="my-3" />

                <div v-if="f.integrantes[1] && esMiIntegrante(f.integrantes[1])">
                  <button @click="abrirHistorial(f.integrantes[1])"
                    class="relative px-3 py-1 bg-gray-600 text-white rounded hover:bg-gray-800 text-sm">
                    <FlagIcon class="w-4 h-4 inline-block mr-1" />
                    Historial

                    <span
                      v-if="badgeRechazados(f.integrantes[1])"
                      class="absolute -top-2 -right-2 min-w-5 h-5 px-1 flex items-center justify-center rounded-full bg-red-600 text-xs font-bold">
                      {{ badgeRechazados(f.integrantes[1]) }}
                    </span>
                  </button>
                </div>

              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-else class="text-gray-500">
        No hay integrantes registrados.
      </p>
      
      <Form v-if="showModal":integrantes="props.integrantes":consejo-id="props.consejo.id"
        @close="cerrarModal"/>
      <HistoryModal :show="showHistorial" :integrante="integranteSeleccionado":historial="historialSeleccionado"
        @close="cerrarHistorial"/>
      <JustificantesAdmin :show="showJustificantes" :justificantes="props.justificantes"
        @close="cerrarJustificantes"/>
    </div>
  </AuthenticatedLayout>
</template>