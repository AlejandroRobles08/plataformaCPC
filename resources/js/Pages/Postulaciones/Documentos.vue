<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { EyeIcon, CloudArrowUpIcon, PaperClipIcon, BellIcon, CheckCircleIcon, XCircleIcon,
  ClockIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/solid'
import { ref, watch, computed } from 'vue'
import Swal from 'sweetalert2'

// Expediente de la postulación visto por la persona postulante: estado de cada documento,
// observaciones de los que no se aprobaron y carga de una nueva versión
const props = defineProps({
  postulacion: { type: Object, required: true },
  nombresDocumentos: { type: Object, default: () => ({}) },
  // Solo mientras la postulación está en revisión se pueden reemplazar documentos
  editable: { type: Boolean, default: false },
})
const page = usePage()

const toast = (icon, title) => Swal.fire({ toast: true, position: 'top-end', icon, title,
  showConfirmButton: false, timer: 3500, timerProgressBar: true })
watch(() => page.props.flash, flash => {
  if (flash?.success) toast('success', flash.success)
  else if (flash?.error) toast('error', flash.error)
}, { immediate: true })

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

const documentos = computed(() =>
  Object.entries(props.nombresDocumentos).map(([key, label]) => ({ key, label }))
)

const tarjetas = computed(() => documentos.value.map(d => {
  const archivo = props.postulacion.documentos?.find(doc => doc.tipo === d.key)
  const estado = archivo?.estatus ?? 'faltante'
  return { ...d, archivo, estado, estilo: ESTILOS[estado],
    subible: props.editable && ['faltante', 'rechazado'].includes(estado) }
}))

const resumen = computed(() => {
  const conteo = { aprobado: 0, pendiente: 0, rechazado: 0, faltante: 0 }
  tarjetas.value.forEach(t => conteo[t.estado]++)
  return conteo
})
const total = computed(() => documentos.value.length)
const porcentajeAprobado = computed(() => total.value ? Math.round(resumen.value.aprobado / total.value * 100) : 0)
const expedienteCompleto = computed(() => total.value && resumen.value.aprobado === total.value)
const hayPorSubir = computed(() => tarjetas.value.some(t => t.subible))

// Formulario de carga
const form = useForm(Object.fromEntries(Object.keys(props.nombresDocumentos).map(k => [k, null])))
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
const submitForm = () => form.post(route('postulaciones.documentos.store'), {
  forceFormData: true,
  preserveScroll: true,
  onSuccess: () => { form.reset(); selectedFiles.value = {} },
})

const verDocumento = archivo => window.open(`/storage/${archivo}`, '_blank')
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Mis documentos" />

    <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6">
      <Link :href="route('postulaciones.index')"
        class="mb-5 inline-flex text-sm font-medium text-gray-600 hover:text-gray-900">
        ← Volver a postulaciones
      </Link>

      <!-- Encabezado con avance del expediente -->
      <section class="mb-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Expediente de postulación</p>
            <h1 class="mt-1 text-2xl font-bold text-gray-900">
              {{ [postulacion.nombre, postulacion.apellido_paterno, postulacion.apellido_materno].filter(Boolean).join(' ') }}
            </h1>
            <p class="mt-1 text-sm text-gray-500">
              La administración revisa cada documento. Si alguno no se aprueba, verá aquí la observación
              y podrá subir una nueva versión en PDF (máx. 2 MB).
            </p>
          </div>
          <div class="md:w-72">
            <div class="mb-1 flex justify-between text-sm">
              <span class="font-medium text-gray-700">Documentos aprobados</span>
              <span class="font-semibold text-gray-900">{{ resumen.aprobado }} de {{ total }}</span>
            </div>
            <div class="h-2.5 overflow-hidden rounded-full bg-gray-200">
              <div class="h-full rounded-full bg-green-600 transition-all duration-500" :style="{ width: `${porcentajeAprobado}%` }"></div>
            </div>
          </div>
        </div>

        <div class="mt-5 flex flex-wrap gap-2">
          <span v-for="(cantidad, estado) in resumen" :key="estado"
            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset" :class="ESTILOS[estado].insignia">
            <component :is="ESTILOS[estado].icono" class="h-4 w-4" />
            {{ ESTILOS[estado].texto }}: {{ cantidad }}
          </span>
        </div>
      </section>

      <div v-if="editable && resumen.rechazado" role="alert"
        class="mb-6 flex gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">
        <ExclamationTriangleIcon class="h-6 w-6 shrink-0 text-red-600" />
        <div>
          <p class="font-semibold">
            {{ resumen.rechazado === 1 ? 'Un documento no fue aprobado' : `${resumen.rechazado} documentos no fueron aprobados` }}
          </p>
          <p class="text-sm text-red-700">Lea la observación de cada uno, corríjalo y súbalo de nuevo para que se revise otra vez.</p>
        </div>
      </div>

      <div v-else-if="expedienteCompleto" class="mb-6 flex gap-3 rounded-xl border border-green-200 bg-green-50 p-4 text-green-800">
        <CheckCircleIcon class="h-6 w-6 shrink-0 text-green-600" />
        <div>
          <p class="font-semibold">Expediente completo y validado</p>
          <p class="text-sm text-green-700">Todos sus documentos fueron aprobados. Su postulación sigue en revisión para la resolución final.</p>
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
                <button type="button" title="Ver" class="rounded p-1.5 text-gray-500 hover:bg-white hover:text-blue-600" @click="verDocumento(t.archivo.archivo)">
                  <EyeIcon class="h-4 w-4" />
                </button>
              </div>

              <p v-if="t.estado === 'pendiente'" class="text-xs text-gray-500">El documento está en revisión por la administración.</p>

              <div v-if="t.estado === 'rechazado' && t.archivo.observacion" class="rounded-lg border border-red-200 bg-red-50 p-3">
                <p class="mb-1 text-xs font-semibold uppercase text-red-700">Observación</p>
                <p class="text-sm text-red-700 whitespace-pre-line break-words">{{ t.archivo.observacion }}</p>
              </div>
              <p v-if="t.subible && t.estado === 'rechazado'" class="text-xs text-gray-600">
                Corrija el documento según la observación y súbalo de nuevo.
              </p>
            </template>
            <p v-else class="text-sm text-gray-500">Aún no se ha subido este documento.</p>

            <!-- Cargar o reemplazar -->
            <div v-if="t.subible" class="mt-auto">
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

        <div v-if="hayPorSubir"
          class="sticky bottom-4 mt-6 flex flex-col gap-3 rounded-xl border border-gray-200 bg-white/95 p-4 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-between">
          <p class="text-sm text-gray-600">
            {{ archivosElegidos
              ? `${archivosElegidos} ${archivosElegidos === 1 ? 'documento listo' : 'documentos listos'} para guardar.`
              : 'Seleccione los documentos que va a subir.' }}
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
