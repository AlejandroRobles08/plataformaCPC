<script setup>
import { ref, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import DraggableModal from '@/Components/DraggableModal.vue'
import Pagination from '@/Components/Pagination.vue'

const FORMULAS_POR_PAGINA = 2
const CARGO_TITULAR = 'Persona Consejera Titular'
const props = defineProps({
  consejoId: {type: [Number, String],required: true},
  integrantes: {type: Array, required: true},
  // Ids con 3 faltas sin justificar: no se les pasa lista hasta que se apruebe su justificante
  integrantesSinPaseLista: {type: Array, default: () => []},
  // Ids sin todos sus documentos del Archivo digital aprobados: se les registra inasistencia en automático
  integrantesSinExpediente: {type: Array, default: () => []},
  sesion: {type: Object, required: true},
  asistencias: {type: Array, default: () => []},
  // Todas las sesiones programadas en la fecha (para cambiar entre sesión 1 y sesión 2)
  sesionesDia: {type: Array, default: () => []},
  // Si todavía se puede programar otra sesión ese día
  puedeAgregarSesion: {type: Boolean, default: false}
})
const emit = defineEmits(['close', 'saved', 'nueva-sesion', 'cambiar-sesion'])
const page = usePage()

// Solo el super_admin puede modificar un pase de lista ya guardado
const esSuperAdmin = computed(() =>
  (page.props.auth.roles ?? []).includes('super_admin')
)

// El pase de lista ya se envió si la sesión tiene asistencias guardadas
const listaGuardada = computed(() => props.asistencias.length > 0)

// Evidencia actual de la sesión (todas las asistencias guardan la misma)
const evidenciaActual = computed(() =>
  props.asistencias.find(a => a.evidencia)?.evidencia ?? null
)

// Admin con la lista ya guardada: solo consulta
const soloLectura = computed(() => listaGuardada.value && !esSuperAdmin.value)

const estaSinPaseLista = integrante =>
  props.integrantesSinPaseLista.some(id => Number(id) === Number(integrante.id))

const estaSinExpediente = integrante =>
  props.integrantesSinExpediente.some(id => Number(id) === Number(integrante.id))

const estadoGuardado = integrante =>
  props.asistencias.find(a => Number(a.integrante_id) === Number(integrante.id))?.estado

// Bloqueado en "No asistió" (se le registra la falta en automático):
// - con 3 faltas sin justificar, en una lista nueva
// - sin expediente aprobado, siempre (salvo que la falta ya se haya justificado)
const bloqueado = integrante =>
  (!listaGuardada.value && estaSinPaseLista(integrante)) ||
  (estaSinExpediente(integrante) && estadoGuardado(integrante) !== 'justificada')

// Lista ya guardada: quienes tienen registro en la sesión. Lista nueva: todos.
// Se fija al abrir el modal porque form.asistencias se construye una sola vez con esta lista.
const integrantesLista = listaGuardada.value
  ? props.integrantes.filter(estadoGuardado)
  : props.integrantes

// Quiénes quedarán con inasistencia por no tener su expediente aprobado (aviso en una lista nueva)
const integrantesSinDocumentos = listaGuardada.value ? [] : props.integrantes.filter(estaSinExpediente)

// "Justificada" no se elige en el pase de lista: solo aparece (marcada) cuando se aprobó el justificante
const opcionesEstado = integrante => [
  { valor: 'asistio', texto: 'Asistió' },
  { valor: 'falto', texto: 'No asistió' },
  ...(estadoGuardado(integrante) === 'justificada' ? [{ valor: 'justificada', texto: 'Justificada' }] : [])
]

// Datos de asistencia.
const form = ref({
  asistencias: integrantesLista.map(i => ({
    integrante_id: i.id,
    estado: bloqueado(i) ? 'falto' : (estadoGuardado(i) ?? 'asistio')
  }))
})

// Integrantes agrupados por fórmula (sin fórmula al final), con la persona titular primero.
// `indice` es la posición del integrante en form.asistencias.
const formulas = computed(() => {
  const grupos = new Map()

  integrantesLista.forEach((integrante, indice) => {
    const numero = integrante.formula ?? null
    if (!grupos.has(numero)) grupos.set(numero, [])
    grupos.get(numero).push({ ...integrante, indice })
  })

  return [...grupos.entries()]
    .map(([numero, integrantes]) => ({
      numero,
      integrantes: integrantes.sort(
        (a, b) => (b.cargo === CARGO_TITULAR) - (a.cargo === CARGO_TITULAR)
      )
    }))
    .sort((a, b) => (a.numero ?? Infinity) - (b.numero ?? Infinity))
})

// Paginación en el navegador: recargar la página cerraría el modal y perdería lo capturado
const paginaActual = ref(1)
const totalPaginas = computed(() =>
  Math.ceil(formulas.value.length / FORMULAS_POR_PAGINA)
)

const formulasPagina = computed(() => {
  const inicio = (paginaActual.value - 1) * FORMULAS_POR_PAGINA
  return formulas.value.slice(inicio, inicio + FORMULAS_POR_PAGINA)
})

// Enlaces con el mismo formato que la paginación de Laravel
const enlacesPaginacion = computed(() => {
  const enlace = (label, pagina) => ({
    label,
    pagina,
    active: false,
    url: pagina >= 1 && pagina <= totalPaginas.value ? '#' : null
  })

  return [
    enlace('&laquo; Anterior', paginaActual.value - 1),
    ...Array.from({ length: totalPaginas.value }, (_, i) => ({
      ...enlace(String(i + 1), i + 1),
      active: i + 1 === paginaActual.value
    })),
    enlace('Siguiente &raquo;', paginaActual.value + 1)
  ]
})

// Páginas que el usuario ya vio, para avisarle si guarda sin revisar las demás
const paginasRevisadas = new Set([paginaActual.value])
watch(paginaActual, pagina => paginasRevisadas.add(pagina))

// Una vez aceptado el aviso no se repite si el guardado falla (ej. falta la evidencia)
const avisoAceptado = ref(false)

// Pide confirmación si hay páginas sin revisar; devuelve true si se puede guardar
async function confirmarPaginasSinRevisar() {
  if (avisoAceptado.value) return true

  const sinRevisar = Array.from({ length: totalPaginas.value }, (_, i) => i + 1)
    .filter(pagina => !paginasRevisadas.has(pagina))

  if (!sinRevisar.length) return true
  const consecuencia = listaGuardada.value
    ? 'Los integrantes de esas páginas conservarán el estado que ya tienen.'
    : 'Los integrantes de esas páginas se registrarán como "Asistió".'

  const { isConfirmed } = await Swal.fire({
    icon: 'warning',
    title: 'Hay fórmulas sin revisar',
    text: `No has revisado ${sinRevisar.length === 1 ? 'la página' : 'las páginas'} ${sinRevisar.join(', ')} del pase de lista. ${consecuencia}`,
    showCancelButton: true,
    confirmButtonText: 'Guardar de todos modos',
    cancelButtonText: 'Revisar',
    confirmButtonColor: '#374151'
  })
  if (isConfirmed) {
    avisoAceptado.value = true
    return true
  }
  // Lleva a la primera página pendiente
  paginaActual.value = sinRevisar[0]
  return false
}

const evidencia = ref(null)
const errorArchivo = ref('')
const errores = ref({})
const procesando = ref(false)

// Valida que la evidencia sea un PDF.
function onFileChange(e) {
  const file = e.target.files[0]
  if (!file) {
    evidencia.value = null
    errorArchivo.value = ''
    return
  }
  if (file.type !== 'application/pdf') {
    errorArchivo.value = 'Solo se permiten archivos PDF.'
    evidencia.value = null
    e.target.value = ''
    return
  }
  errorArchivo.value = ''
  evidencia.value = file
}

// Guarda las asistencias de la sesión.
async function submitForm() {
  if (procesando.value || soloLectura.value) return
  if (!(await confirmarPaginasSinRevisar())) return

  errores.value = {}
  const formData = new FormData()

  // Datos de la sesión.
  formData.append('fecha', props.sesion.fecha)
  formData.append('tipo_sesion', props.sesion.tipo_sesion)

  // Asistencias.
  form.value.asistencias.forEach((a, index) => {
    formData.append(`asistencias[${index}][integrante_id]`, a.integrante_id)
    formData.append(`asistencias[${index}][estado]`, a.estado)
  })

  // Evidencia.
  if (evidencia.value) formData.append('evidencia', evidencia.value)
  procesando.value = true
  router.post(
    route('asistencias.sesion.store', props.consejoId),
    formData,
    {
      forceFormData: true,
      preserveScroll: true,
      // Con errores de validación el modal sigue abierto para mostrarlos y conservar lo capturado
      preserveState: 'errors',

      onSuccess: () => {
        Swal.fire({
          icon: 'success',
          title: '¡Asistencia guardada!',
          text: 'El pase de lista se registró correctamente.',
          confirmButtonText: 'Aceptar',
          confirmButtonColor: '#374151'
        }).then(() => {
          emit('saved')
          emit('close')
        })
      },
      // Errores de validación del backend (evidencia obligatoria, lista ya guardada, etc.)
      onError: (errors) => {
        errores.value = errors
      },
      onFinish: () => {
        procesando.value = false
      }
    }
  )
}
</script>

<template>
  <DraggableModal :title="`Registrar asistencia - ${sesion.fecha}`" max-width="max-w-2xl" @close="emit('close')">
    <!-- Selector de sesión cuando hay más de una en el día -->
    <div v-if="sesionesDia.length > 1" class="mb-4">
      <p class="mb-2 text-sm font-medium">Sesiones de este día</p>
      <div class="flex flex-wrap gap-2">
        <button v-for="(s, idx) in sesionesDia" :key="s.id" type="button" class="rounded px-3 py-1.5 text-sm capitalize transition disabled:opacity-50"
          :class="Number(s.id) === Number(sesion.id) ? 'bg-gray-700 text-white' : 'bg-gray-200 text-gray-800 hover:bg-gray-300'"
          :disabled="procesando" @click="Number(s.id) !== Number(sesion.id) && emit('cambiar-sesion', s)">
          Sesión {{ idx + 1 }} · {{ s.tipo_sesion }}<template v-if="s.hora_inicio"> · {{ s.hora_inicio.slice(0, 5) }}</template>
        </button>
      </div>
    </div>

    <!-- Pase de lista ya enviado -->
    <div v-if="listaGuardada" class="mb-4 rounded-lg border border-green-200 bg-green-100 px-4 py-3 text-green-800">Lista de asistencia guardada correctamente.</div>

    <!-- Datos de la sesión -->
    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
      <label class="block text-sm font-medium">Fecha
        <input type="date" :value="sesion.fecha" readonly class="mt-1 w-full rounded border bg-gray-100 px-3 py-2 font-normal"/>
      </label>
      <label class="block text-sm font-medium">Tipo de sesión
        <input type="text" :value="sesion.tipo_sesion" readonly class="mt-1 w-full rounded border bg-gray-100 px-3 py-2 font-normal capitalize"/>
      </label>
      <!-- Horario (solo en sesiones programadas con horario) -->
      <label v-if="sesion.hora_inicio && sesion.hora_fin" class="block text-sm font-medium sm:col-span-2">Horario
        <input type="text" readonly class="mt-1 w-full rounded border bg-gray-100 px-3 py-2 font-normal" :value="`${sesion.hora_inicio.slice(0, 5)} a ${sesion.hora_fin.slice(0, 5)} hrs`"/>
      </label>
    </div>

    <!-- Integrantes con inasistencia automática por expediente incompleto -->
    <div v-if="integrantesSinDocumentos.length" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
      <p class="font-semibold">Integrantes con expediente incompleto: se les registrará inasistencia. Podrán justificarla cuando su expediente esté completo y aprobado.</p>
      <ul class="mt-1 list-inside list-disc">
        <li v-for="i in integrantesSinDocumentos" :key="i.id">{{ i.nombre }} {{ i.apellido }}</li>
      </ul>
    </div>

    <!-- Integrantes -->
    <div class="mb-4 rounded border p-3">
      <p class="mb-2 text-sm font-medium">Integrantes</p>

      <!-- Altura mínima para que la paginación no se mueva en una página con una sola fórmula -->
      <div :class="{ 'sm:min-h-48': totalPaginas > 1 }">
        <div v-for="formula in formulasPagina" :key="formula.numero ?? 'sin-formula'" class="mb-2 last:mb-0">
          <p class="rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">{{ formula.numero ? `Fórmula ${formula.numero}` : 'Sin fórmula' }}</p>

          <div v-for="i in formula.integrantes" :key="i.id" class="flex flex-col gap-1 border-b py-1.5 last:border-b-0 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
            <p class="text-sm font-semibold">
              {{ i.nombre }} {{ i.apellido }}
              <!-- 3 faltas sin justificar: no se le pasa lista hasta que se apruebe su justificante -->
              <span v-if="estaSinPaseLista(i)" class="block text-xs font-medium text-red-600" title="Se le registra la falta en automático hasta que se apruebe su justificante">
                ⚠ 3 faltas sin justificar: sin pase de lista hasta justificar
              </span>
              <!-- Sin expediente aprobado: inasistencia automática, justificable al completar el expediente -->
              <span v-else-if="estaSinExpediente(i) && bloqueado(i)" class="block text-xs font-medium text-amber-600" title="Podrá justificarla cuando su expediente esté completo y aprobado">
                ⚠ Expediente incompleto: inasistencia automática
              </span>
            </p>

            <!-- Casillas bloqueadas en solo lectura o con 3 faltas sin justificar (el estado solo cambia al aprobar un justificante) -->
            <div class="flex shrink-0 flex-wrap gap-3 text-sm" :class="{ 'opacity-60': soloLectura || bloqueado(i) }">
              <label v-for="op in opcionesEstado(i)" :key="op.valor" class="flex items-center gap-1">
                <input type="radio" :name="`estado-${i.id}`" :value="op.valor" :disabled="soloLectura || bloqueado(i) || op.valor === 'justificada'" v-model="form.asistencias[i.indice].estado"/>
                {{ op.texto }}
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- Paginación por fórmulas -->
      <div v-if="totalPaginas > 1" class="mt-2 flex justify-end border-t pt-2">
        <Pagination :links="enlacesPaginacion" @cambiar-pagina="paginaActual = $event" />
      </div>
    </div>

    <!-- Evidencia -->
    <div class="mb-4">
      <p class="mb-1 text-sm font-medium">Evidencia documental (PDF)</p>

      <!-- Evidencia ya guardada -->
      <a v-if="evidenciaActual" :href="`/storage/${evidenciaActual}`" target="_blank" rel="noopener"
        class="mb-2 block rounded border bg-gray-100 px-3 py-2 font-medium text-blue-700 underline hover:text-blue-900">Ver evidencia documental</a>

      <!-- El admin ya no puede reemplazarla; el super_admin sí -->
      <template v-if="!soloLectura">
        <p v-if="evidenciaActual" class="mb-1 text-xs text-gray-500">Adjunta un archivo solo si quieres reemplazar la evidencia actual.</p>
        <input type="file" accept="application/pdf" class="w-full rounded border px-3 py-2" @change="onFileChange"/>
        <p v-if="errorArchivo || errores.evidencia" class="mt-1 text-sm text-red-500">{{ errorArchivo || errores.evidencia }}</p>
        <p class="mt-1 text-xs text-gray-500">Solo se permiten archivos PDF de hasta 4 MB.</p>
      </template>

      <p v-else-if="!evidenciaActual" class="text-sm text-gray-500">Sin evidencia documental.</p>
    </div>

    <!-- Errores generales del backend -->
    <p v-if="errores.asistencias || errores.fecha" class="mb-4 text-sm text-red-600">{{ errores.asistencias || errores.fecha }}</p>

    <!-- Botones -->
    <div class="flex items-center gap-2 border-t pt-4">
      <!-- Programar otra sesión el mismo día (extrema izquierda) -->
      <button v-if="puedeAgregarSesion" type="button" class="rounded border border-gray-400 px-4 py-2 text-sm transition hover:bg-gray-100 disabled:opacity-50"
        :disabled="procesando" title="Programar una sesión adicional en esta misma fecha" @click="emit('nueva-sesion')">+ Otra sesión este día</button>

      <button type="button" class="ml-auto rounded bg-gray-300 px-4 py-2 transition hover:bg-gray-400 disabled:opacity-50" :disabled="procesando" @click="emit('close')">Cancelar</button>

      <button type="button" class="rounded bg-gray-700 px-4 py-2 text-white transition hover:bg-gray-800 disabled:opacity-50"
        :disabled="procesando || soloLectura || !integrantesLista.length" @click="submitForm">{{ procesando ? 'Guardando...' : 'Guardar asistencia' }}</button>
    </div>
  </DraggableModal>
</template>
