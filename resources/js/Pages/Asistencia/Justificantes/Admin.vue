<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import DraggableModal from '@/Components/DraggableModal.vue'

const props = defineProps({
  show: Boolean,
  justificantes: { type: Array, default: () => [] }
})
const emit = defineEmits(['close'])
const seleccionado = ref(null)
const busqueda = ref('')
const filtro = ref('todos')

// Estilo y texto de cada estado del justificante
const estados = {
  pendiente: { label: 'Pendiente', clase: 'bg-amber-100 text-amber-800 ring-amber-300', borde: 'border-l-amber-400' },
  aprobado: { label: 'Aprobado', clase: 'bg-green-100 text-green-800 ring-green-300', borde: 'border-l-green-500' },
  rechazado: { label: 'Rechazado', clase: 'bg-red-100 text-red-800 ring-red-300', borde: 'border-l-red-500' }
}

const nombre = j => `${j.integrante?.nombre ?? ''} ${j.integrante?.apellido ?? ''}`.trim()
const iniciales = j => nombre(j).split(' ').slice(0, 2).map(p => p[0]).join('').toUpperCase()
const contar = estado => props.justificantes.filter(j => estado === 'todos' || j.estado_justificante === estado).length

// Filtra por estado y por texto (nombre, fecha o tipo de sesión); los pendientes van primero
const filtrados = computed(() => {
  const texto = busqueda.value.trim().toLowerCase()
  return props.justificantes
    .filter(j => filtro.value === 'todos' || j.estado_justificante === filtro.value)
    .filter(j => !texto || `${nombre(j)} ${j.fecha} ${j.tipo_sesion}`.toLowerCase().includes(texto))
    .sort((a, b) => (b.estado_justificante === 'pendiente') - (a.estado_justificante === 'pendiente'))
})

function cerrar() {
  seleccionado.value = null
  emit('close')
}

// Envía la validación (aprobar / rechazar) y muestra el resultado
function validar(ruta, datos, exito, errorDefault) {
  router.patch(route(ruta, seleccionado.value.id), datos, {
    preserveScroll: true,
    onSuccess: () => { cerrar(); Swal.fire({ icon: 'success', ...exito }) },
    onError: e => Swal.fire({ icon: 'error', title: 'Error', text: e.comentario_justificante ?? e.justificante ?? errorDefault })
  })
}

function aprobar() {
  Swal.fire({
    title: '¿Aprobar justificante?', text: 'La asistencia será marcada como justificada.', icon: 'question',
    showCancelButton: true, confirmButtonText: 'Aprobar', cancelButtonText: 'Cancelar', confirmButtonColor: '#16a34a'
  }).then(r => r.isConfirmed && validar('justificantes.aprobar', {},
    { title: 'Justificante aprobado', text: 'La asistencia fue marcada como justificada.' },
    'No fue posible aprobar el justificante.'))
}

function rechazar() {
  Swal.fire({
    title: 'No aprobar justificante.', text: 'La asistencia permanecerá como falta.', icon: 'warning',
    input: 'textarea', inputPlaceholder: 'Describa los motivos por los que no se aprobará el justificante.',
    inputAttributes: { maxlength: 1000 }, inputValidator: v => !v?.trim() && 'Indica el motivo del rechazo.',
    showCancelButton: true, confirmButtonText: 'No aprobar', cancelButtonText: 'Cancelar', confirmButtonColor: '#b91c1c'
  }).then(r => r.isConfirmed && validar('justificantes.rechazar', { comentario_justificante: r.value.trim() },
    { title: 'Justificante rechazado', text: 'La asistencia permanece marcada como falta.' },
    'No fue posible rechazar el justificante.'))
}
</script>

<template>
  <DraggableModal v-if="show" max-width="max-w-3xl"
    :title="seleccionado ? (seleccionado.estado_justificante === 'pendiente' ? 'Revisar justificante' : 'Ver justificante') : 'Justificantes'"
    @close="cerrar">
    <!-- Lista de justificantes -->
    <div v-if="!seleccionado">
      <!-- Buscador -->
      <div class="relative mb-3">
        <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" />
        </svg>
        <input v-model="busqueda" type="search" placeholder="Buscar por integrante, fecha o tipo de sesión..."
          class="w-full rounded-lg border-gray-300 py-2 pl-9 pr-3 text-sm focus:border-gray-600 focus:ring-gray-600" />
      </div>

      <!-- Filtros por estado con contador -->
      <div class="mb-4 flex flex-wrap gap-2">
        <button v-for="(e, clave) in { todos: { label: 'Todos' }, ...estados }" :key="clave" type="button"
          class="rounded-full px-3 py-1 text-xs font-semibold transition"
          :class="filtro === clave ? 'bg-gray-800 text-white shadow' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
          @click="filtro = clave">
          {{ e.label }} <span class="ml-1 opacity-75">{{ contar(clave) }}</span>
        </button>
      </div>

      <div class="max-h-[60vh] space-y-2 overflow-y-auto pr-1">
        <div v-for="j in filtrados" :key="j.id"
          class="flex items-center gap-4 rounded-lg border border-l-4 bg-white p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
          :class="estados[j.estado_justificante]?.borde">
          <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gray-700 font-bold text-white">
            {{ iniciales(j) }}
          </div>
          <div class="min-w-0 flex-1">
            <p class="truncate font-semibold text-gray-800">{{ nombre(j) }}</p>
            <p class="text-sm capitalize text-gray-500"> {{ j.fecha }} · Sesión {{ j.tipo_sesion }}</p>
          </div>
          <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1" :class="estados[j.estado_justificante]?.clase">
            {{ estados[j.estado_justificante]?.label ?? j.estado_justificante }}
          </span>
          <button type="button" class="w-24 rounded-lg px-4 py-2 text-sm font-medium text-white transition"
            :class="j.estado_justificante === 'pendiente' ? 'bg-gray-800 hover:bg-black' : 'bg-gray-400 hover:bg-gray-500'"
            @click="seleccionado = j">
            {{ j.estado_justificante === 'pendiente' ? 'Revisar' : 'Ver' }}
          </button>
        </div>

        <p v-if="!filtrados.length" class="py-10 text-center text-gray-500">
          {{ justificantes.length ? 'No hay justificantes que coincidan con la búsqueda.' : 'No hay justificantes subidos aún.' }}
        </p>
      </div>
    </div>

    <!-- Detalle del justificante -->
    <div v-else>
      <button type="button" class="mb-3 text-sm text-gray-600 hover:underline" @click="seleccionado = null">← Volver</button>

      <div class="mb-4 flex items-center gap-3">
        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-700 text-lg font-bold text-white">
          {{ iniciales(seleccionado) }}
        </div>
        <div class="flex-1">
          <h3 class="text-lg font-bold">{{ nombre(seleccionado) }}</h3>
          <p class="text-sm capitalize text-gray-500"> {{ seleccionado.fecha }} · Sesión {{ seleccionado.tipo_sesion }}</p>
        </div>
        <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1" :class="estados[seleccionado.estado_justificante]?.clase">
          {{ estados[seleccionado.estado_justificante]?.label }}
        </span>
      </div>

      <!-- Visualización del PDF -->
      <iframe :src="route('justificantes.show', seleccionado.id)" class="mb-4 h-[450px] w-full rounded-lg border"></iframe>

      <!-- Acciones de validación -->
      <div v-if="seleccionado.estado_justificante === 'pendiente'" class="flex justify-end gap-3">
        <button type="button" class="rounded-lg bg-red-700 px-4 py-2 text-white hover:bg-red-900" @click="rechazar">No aprobar</button>
        <button type="button" class="rounded-lg bg-green-700 px-4 py-2 text-white hover:bg-green-900" @click="aprobar">Aprobar</button>
      </div>

      <!-- Ya validado -->
      <div v-else class="rounded-lg p-3 text-sm ring-1" :class="estados[seleccionado.estado_justificante]?.clase">
        Este justificante ya fue {{ seleccionado.estado_justificante }}.
        <p v-if="seleccionado.comentario_justificante" class="mt-1"><strong>Motivo:</strong> {{ seleccionado.comentario_justificante }}</p>
      </div>
    </div>
  </DraggableModal>
</template>
