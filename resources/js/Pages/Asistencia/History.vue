<script setup>
import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import DraggableModal from '@/Components/DraggableModal.vue'
import * as XLSX from 'xlsx'

const props = defineProps({
  integrante: Object,
  historial: Array,
  show: Boolean
})

const emit = defineEmits(['close'])

// El integrante no puede exportar su historial; solo la administración
const page = usePage()
const esIntegrante = computed(() => page.props.auth?.roles?.includes('integrante') ?? false)

function textoEstado(estado) {
  switch (estado) {
    case 'asistio': return 'Asistió'
    case 'falto': return 'Inasistencia'
    case 'justificada': return 'Inasistencia justificada'
    default: return '-'
  }
}

function claseEstado(estado) {
  switch (estado) {
    case 'asistio': return 'text-green-600 font-semibold'
    case 'falto': return 'text-red-600 font-semibold'
    case 'justificada': return 'text-yellow-600 font-semibold'
    default: return 'text-gray-500'
  }
}

// Devuelve el motivo del rechazo solo si el justificante fue rechazado
function comentarioRechazo(h) {
  return h.estado_justificante === 'rechazado' ? h.comentario_justificante : null
}

// Filas del historial: una falta justificada se muestra como dos filas (la inasistencia y su justificación)
const filas = computed(() =>
  (props.historial ?? []).flatMap(h =>
    h.estado === 'justificada'
      ? [
          { ...h, estado: 'falto', clave: `${h.id}-falto` },
          { ...h, clave: `${h.id}-justificada` }
        ]
      : [{ ...h, clave: `${h.id}` }]
  )
)

// Buscador (tipo de sesión, fecha, estado o comentario) y paginación de 10 en 10
const POR_PAGINA = 10
const busqueda = ref('')
const pagina = ref(1)

const filtradas = computed(() => {
  const texto = busqueda.value.trim().toLowerCase()
  return texto
    ? filas.value.filter(h => `${h.tipo_sesion} ${h.fecha} ${textoEstado(h.estado)} ${comentarioRechazo(h) ?? ''}`.toLowerCase().includes(texto))
    : filas.value
})
const totalPaginas = computed(() => Math.max(1, Math.ceil(filtradas.value.length / POR_PAGINA)))
const filasPagina = computed(() => filtradas.value.slice((pagina.value - 1) * POR_PAGINA, pagina.value * POR_PAGINA))

// Al buscar o cambiar de integrante se regresa a la primera página
watch([busqueda, () => props.integrante?.id], () => { pagina.value = 1 })

// export a excel
function exportarExcel() {

  const data = filas.value.map(h => ({
    'Tipo de sesión': h.tipo_sesion,
    'Fecha': h.fecha,
    'Estado': textoEstado(h.estado),
    'Comentarios': comentarioRechazo(h) ?? ''
  }))

  const worksheet = XLSX.utils.json_to_sheet(data)
  const workbook = XLSX.utils.book_new()

  XLSX.utils.book_append_sheet(workbook, worksheet, "Historial")

  XLSX.writeFile(
    workbook,
    `Historial_${props.integrante.nombre}_${props.integrante.apellido}.xlsx`
  )
}
</script>


<template>
  <DraggableModal
    v-if="show"
    :title="`Historial de ${integrante.nombre} ${integrante.apellido}`"
    @close="emit('close')">
    <div class="mb-3 text-left">
      <p class="text-sm text-gray-600">
        <strong>Importante:</strong> Las faltas justificadas se muestran como dos filas: la inasistencia y su justificación.
      </p>
    </div>
    <div class="mb-3 flex flex-wrap items-center gap-3">
      <input v-model="busqueda" type="search" placeholder="Buscar por tipo de sesión, fecha o estado..."
        class="flex-1 rounded border-gray-300 px-3 py-2 text-sm focus:border-gray-600 focus:ring-gray-600" />
      <button v-if="!esIntegrante" @click="exportarExcel" class="px-4 py-2 bg-green-600 
      text-white rounded hover:bg-green-700 text-sm">
        Exportar Excel
      </button>
    </div>

    <div class="max-h-[60vh] overflow-y-auto">
      <table class="w-full border border-gray-300 rounded text-sm">
        <thead class="bg-gray-100">
          <tr>
            <th class="px-3 py-2 border">Tipo de sesión</th>
            <th class="px-3 py-2 border">Fecha</th>
            <th class="px-3 py-2 border">Estado</th>
            <th class="px-3 py-2 border">Comentarios</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="h in filasPagina"
            :key="h.clave" class="hover:bg-gray-50">
            <td class="px-3 py-2 border capitalize">
              {{ h.tipo_sesion }}
            </td>

            <td class="px-3 py-2 border">
              {{ h.fecha }}
            </td>

            <td class="px-3 py-2 border">
              <span :class="claseEstado(h.estado)">
                {{ textoEstado(h.estado) }}
              </span>
            </td>

            <!-- Motivo del rechazo del justificante (solo si fue rechazado) -->
            <td class="px-3 py-2 border whitespace-pre-line break-words">
              <span v-if="comentarioRechazo(h)" class="text-red-700">
                {{ comentarioRechazo(h) }}
              </span>
              <span v-else class="text-gray-400">-</span>
            </td>
          </tr>
          <tr v-if="!filasPagina.length">
            <td colspan="4" class="px-3 py-6 text-center text-gray-500">
              {{ busqueda ? 'No hay registros que coincidan con la búsqueda.' : 'Sin registros de asistencia.' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <div v-if="totalPaginas > 1" class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm">
      <span class="text-gray-500">
        {{ (pagina - 1) * POR_PAGINA + 1 }}–{{ Math.min(pagina * POR_PAGINA, filtradas.length) }} de {{ filtradas.length }}
      </span>
      <div class="flex gap-1">
        <button :disabled="pagina === 1" class="rounded border px-3 py-1 hover:bg-gray-100 disabled:opacity-40" @click="pagina--">‹</button>
        <button v-for="n in totalPaginas" :key="n" class="rounded border px-3 py-1"
          :class="n === pagina ? 'bg-gray-700 text-white' : 'hover:bg-gray-100'" @click="pagina = n">{{ n }}</button>
        <button :disabled="pagina === totalPaginas" class="rounded border px-3 py-1 hover:bg-gray-100 disabled:opacity-40" @click="pagina++">›</button>
      </div>
    </div>

    <div class="text-right mt-4">
      <button @click="emit('close')"class="px-4 py-2 bg-gray-600 text-white 
        rounded hover:bg-gray-800">
        Cerrar
      </button>
    </div>
  </DraggableModal>
</template>