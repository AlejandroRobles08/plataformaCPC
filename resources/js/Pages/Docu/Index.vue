<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, useForm, usePage, router } from '@inertiajs/vue3'
import { EyeIcon, CloudArrowUpIcon, ArrowDownTrayIcon, PaperClipIcon, BellIcon, CheckCircleIcon, XCircleIcon,
  ClockIcon, HandThumbUpIcon, HandThumbDownIcon } from '@heroicons/vue/24/solid'
import { ref, watch, computed } from 'vue'
import Swal from 'sweetalert2'

const props = defineProps({ integrante: Object })
const page = usePage()

// Roles: admin y super_admin revisan; integrante y super_admin suben documentos
const roles = computed(() => page.props.auth?.roles ?? [])
const isReviewer = computed(() => roles.value.includes('admin') || roles.value.includes('super_admin'))
const isIntegrante = computed(() => roles.value.includes('integrante'))
const canUpload = computed(() => isIntegrante.value || roles.value.includes('super_admin'))

// Mensajes flash (el warning llega al ser redirigido con el expediente sin aprobar)
const toast = (icon, title) => Swal.fire({ toast: true, position: 'top-end', icon, title,
  showConfirmButton: false, timer: 3500, timerProgressBar: true })
// Se observa el objeto flash completo (nuevo en cada respuesta) para que el aviso
// aparezca aunque el mensaje sea el mismo que el anterior (p. ej. dos cargas seguidas)
watch(() => page.props.flash, flash => {
  if (flash?.success) toast('success', flash.success)
  else if (flash?.error) toast('error', flash.error)
  else if (flash?.warning) Swal.fire({ icon: 'warning', title: 'Documentos pendientes',
    text: flash.warning, confirmButtonText: 'Entendido' })
}, { immediate: true })

const documentos = [
  { key: 'ine', label: 'INE' },
  { key: 'comprobante_domiciliario', label: 'Comprobante domiciliario' },
  { key: 'bajo_protesta_art_170', label: 'Bajo protesta art. 170' },
  { key: 'integracion_formula', label: 'Integración de fórmula' },
  { key: 'curriculum_vitae', label: 'Currículum Vitae' },
  { key: 'carta_motivos', label: 'Carta de motivos' },
  { key: 'cumplimiento_normatividad', label: 'Cumplimiento de normatividad' },
]

// Estilos por estado
const ESTILOS = {
  aprobado: { texto: 'Aprobado', icono: CheckCircleIcon, insignia: 'bg-green-100 text-green-800 ring-green-200',
    borde: 'border-l-green-500', iconoColor: 'text-green-600 bg-green-50' },
  pendiente: { texto: 'En revisión', icono: ClockIcon, insignia: 'bg-amber-100 text-amber-800 ring-amber-200',
    borde: 'border-l-amber-400', iconoColor: 'text-amber-600 bg-amber-50' },
  rechazado: { texto: 'No aprobado', icono: XCircleIcon, insignia: 'bg-red-100 text-red-800 ring-red-200',
    borde: 'border-l-red-500', iconoColor: 'text-red-600 bg-red-50' },
  faltante: { texto: 'Sin cargar', icono: BellIcon, insignia: 'bg-gray-100 text-gray-700 ring-gray-200',
    borde: 'border-l-gray-300', iconoColor: 'text-gray-500 bg-gray-100' },
}

// Cada tarjeta con su documento guardado (si existe) y su estado
const tarjetas = computed(() => documentos.map(d => {
  const archivo = props.integrante.documentos?.find(doc => doc.tipo === d.key)
  const estado = archivo?.estatus ?? 'faltante'
  // Solo se sube un documento que falta o que fue rechazado
  return { ...d, archivo, estado, estilo: ESTILOS[estado], subible: ['faltante', 'rechazado'].includes(estado) }
}))

const resumen = computed(() => {
  const conteo = { aprobado: 0, pendiente: 0, rechazado: 0, faltante: 0 }
  tarjetas.value.forEach(t => conteo[t.estado]++)
  return conteo
})
const porcentajeAprobado = computed(() => Math.round(resumen.value.aprobado / documentos.length * 100))
const expedienteCompleto = computed(() => resumen.value.aprobado === documentos.length)
// Sin documentos por subir (todos en revisión o aprobados) no se muestra "Guardar documentos"
const hayPorSubir = computed(() => tarjetas.value.some(t => t.subible))

// Formulario de carga
const form = useForm(Object.fromEntries(documentos.map(d => [d.key, null])))
const selectedFiles = ref({})
const archivosElegidos = computed(() => Object.keys(selectedFiles.value).length)

const elegirArchivo = (event, key) => {
  const file = event.target.files[0]
  if (file) {
    form[key] = file
    selectedFiles.value[key] = file.name
  }
  event.target.value = '' // permite volver a elegir el mismo archivo tras quitarlo
}
const quitarArchivo = key => {
  form[key] = null
  delete selectedFiles.value[key]
}
const submitForm = () => form.post(route('docu.store', props.integrante.id), {
  forceFormData: true,
  onSuccess: () => { form.reset(); selectedFiles.value = {} },
})

// Validación del administrador
const aprobarDocumento = async doc => {
  const { isConfirmed } = await Swal.fire({ title: '¿Aprobar documento?', text: 'El documento será marcado como aprobado.',
    icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, aprobar', cancelButtonText: 'Cancelar',
    confirmButtonColor: '#15803d' })
  if (isConfirmed) router.patch(route('docu.aprobar', doc.id), {}, { preserveScroll: true })
}
const rechazarDocumento = async doc => {
  const { isConfirmed, value } = await Swal.fire({ title: 'No se aprobará el documento',
    text: 'Indique las observaciones para el integrante.', input: 'textarea', inputLabel: 'Observación',
    inputPlaceholder: 'Describa las razones por las que no se aprobará el documento.',
    inputAttributes: { 'aria-label': 'Observación del documento' },
    showCancelButton: true, confirmButtonText: 'No aprobar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc2626',
    inputValidator: v => !v && 'Debes escribir una observación.' })
  if (isConfirmed) router.patch(route('docu.rechazar', doc.id), { observacion: value }, { preserveScroll: true })
}
</script>

<template>
  <AuthenticatedLayout>
    <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6">
      <Link :href="isReviewer ? route('consejos.integrantes', integrante.consejo_id) : route('dashboard')"
        class="mb-5 inline-flex text-sm font-medium text-gray-600 hover:text-gray-900">
        ← {{ isReviewer ? 'Volver a Integrantes' : 'Volver al inicio' }}
      </Link>

      <!-- Encabezado con avance del expediente -->
      <section class="mb-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Archivo digital</p>
            <h1 class="mt-1 text-2xl font-bold text-gray-900">{{ integrante.nombre_completo }}</h1>
            <p class="mt-1 text-sm text-gray-500">
              {{ isReviewer
                ? 'Revisa cada documento y apruébalo o indica las observaciones para su corrección.'
                : 'Sube tus documentos en PDF (máx. 2 MB). Al aprobarse todos podrás usar los demás módulos.' }}
            </p>
          </div>
          <div class="md:w-72">
            <div class="mb-1 flex justify-between text-sm">
              <span class="font-medium text-gray-700">Documentos aprobados</span>
              <span class="font-semibold text-gray-900">{{ resumen.aprobado }} de {{ documentos.length }}</span>
            </div>
            <div class="h-2.5 overflow-hidden rounded-full bg-gray-200">
              <div class="h-full rounded-full bg-green-600 transition-all duration-500" :style="{ width: `${porcentajeAprobado}%` }"></div>
            </div>
          </div>
        </div>

        <!-- Resumen por estado -->
        <div class="mt-5 flex flex-wrap gap-2">
          <span v-for="(cantidad, estado) in resumen" :key="estado"
            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset" :class="ESTILOS[estado].insignia">
            <component :is="ESTILOS[estado].icono" class="h-4 w-4" />
            {{ ESTILOS[estado].texto }}: {{ cantidad }}
          </span>
        </div>
      </section>

      <div v-if="expedienteCompleto" class="mb-6 flex gap-3 rounded-xl border border-green-200 bg-green-50 p-4 text-green-800">
        <CheckCircleIcon class="h-6 w-6 shrink-0 text-green-600" />
        <div>
          <p class="font-semibold">Expediente completo y validado</p>
          <p class="text-sm text-green-700">Todos los documentos fueron revisados y aprobados. Ya no es necesario cargar más archivos.</p>
        </div>
      </div>

      <form @submit.prevent="submitForm">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
          <article v-for="t in tarjetas" :key="t.key"
            class="flex flex-col gap-4 rounded-xl border border-l-4 border-gray-200 bg-white p-5 shadow-sm hover:shadow-md" :class="t.estilo.borde">
            <header class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" :class="t.estilo.iconoColor">
                  <component :is="t.estilo.icono" class="h-5 w-5" />
                </span>
                <h2 class="text-sm font-semibold text-gray-900">{{ t.label }}</h2>
              </div>
              <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset" :class="t.estilo.insignia">{{ t.estilo.texto }}</span>
            </header>

            <template v-if="t.archivo">
              <div class="flex items-center justify-between gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">
                <span class="flex min-w-0 items-center gap-1.5"><PaperClipIcon class="h-4 w-4 shrink-0 text-gray-400" /><span class="truncate">{{ t.label }}.pdf</span></span>
                <span class="flex shrink-0 gap-1 text-gray-500">
                  <a :href="route('docu.show', t.archivo.id)" target="_blank" title="Ver" class="rounded p-1.5 hover:bg-white hover:text-blue-600"><EyeIcon class="h-4 w-4" /></a>
                  <a :href="route('docu.download', t.archivo.id)" title="Descargar" class="rounded p-1.5 hover:bg-white hover:text-indigo-600"><ArrowDownTrayIcon class="h-4 w-4" /></a>
                </span>
              </div>

              <p v-if="t.estado === 'pendiente'" class="text-xs text-gray-500">
                {{ isReviewer ? 'Este documento espera tu revisión.' : 'El documento está en revisión por la administración.' }}
              </p>

              <div v-if="t.estado === 'rechazado' && t.archivo.observacion" class="rounded-lg border border-red-200 bg-red-50 p-3">
                <p class="mb-1 text-xs font-semibold uppercase text-red-700">Observación</p>
                <p class="text-sm text-red-700">{{ t.archivo.observacion }}</p>
              </div>

              <!-- Rechazado: no se valida de nuevo hasta que el integrante suba otra versión -->
              <p v-if="isReviewer && t.estado === 'rechazado'"
                class="mt-auto flex items-center gap-1.5 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600">
                <ClockIcon class="h-4 w-4 shrink-0 text-gray-400" />
                En espera de que el integrante suba una nueva versión del documento.
              </p>
              <p v-else-if="!isReviewer && t.estado === 'rechazado'" class="text-xs text-gray-600">
                Corrige el documento según la observación y súbelo de nuevo para que se revise otra vez.
              </p>

              <div v-if="isReviewer && t.estado === 'pendiente'" class="mt-auto flex gap-2 text-sm font-medium">
                <button type="button" @click="aprobarDocumento(t.archivo)"
                  class="inline-flex flex-1 items-center justify-center gap-1 rounded-lg bg-green-600 px-3 py-2 text-white hover:bg-green-700">
                  <HandThumbUpIcon class="h-4 w-4" /> Aprobar
                </button>
                <button type="button" @click="rechazarDocumento(t.archivo)"
                  class="inline-flex flex-1 items-center justify-center gap-1 rounded-lg border border-red-300 px-3 py-2 text-red-700 hover:bg-red-50">
                  <HandThumbDownIcon class="h-4 w-4" /> No aprobar
                </button>
              </div>
            </template>
            <p v-else class="text-sm text-gray-500">Aún no se ha subido este documento.</p>

            <!-- Cargar o reemplazar -->
            <div v-if="canUpload && t.subible" class="mt-auto">
              <div v-if="selectedFiles[t.key]"
                class="flex items-center justify-between gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800">
                <span class="flex min-w-0 items-center gap-1.5"><PaperClipIcon class="h-4 w-4 shrink-0" /><span class="truncate">{{ selectedFiles[t.key] }}</span></span>
                <button type="button" title="Quitar" class="rounded p-1 text-blue-700 hover:bg-blue-100" @click="quitarArchivo(t.key)"><XCircleIcon class="h-4 w-4" /></button>
              </div>
              <label v-else
                class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 p-3 text-sm font-medium text-gray-600 hover:border-gray-400 hover:bg-gray-50">
                <CloudArrowUpIcon class="h-5 w-5 text-gray-400" />
                {{ t.estado === 'rechazado' ? 'Reemplazar documento' : 'Seleccionar PDF' }}
                <input type="file" accept="application/pdf" class="hidden" @change="elegirArchivo($event, t.key)" />
              </label>
            </div>

            <p v-if="form.errors[t.key]" class="text-xs text-red-600">{{ form.errors[t.key] }}</p>
          </article>
        </div>

        <p v-if="form.errors.documentos" class="mt-4 text-sm text-red-600">{{ form.errors.documentos }}</p>

        <!-- Guardar documentos: se oculta cuando ya no queda nada por subir -->
        <div v-if="canUpload && hayPorSubir"
          class="sticky bottom-4 mt-6 flex flex-col gap-3 rounded-xl border border-gray-200 bg-white/95 p-4 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-between">
          <p class="text-sm text-gray-600">
            {{ archivosElegidos
              ? `${archivosElegidos} ${archivosElegidos === 1 ? 'documento listo' : 'documentos listos'} para guardar.`
              : 'Selecciona los documentos que vas a subir.' }}
          </p>
          <button type="submit" :disabled="form.processing || !archivosElegidos"
            class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-green-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50">
            <CloudArrowUpIcon class="h-5 w-5" /> {{ form.processing ? 'Guardando...' : 'Guardar documentos' }}
          </button>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>
