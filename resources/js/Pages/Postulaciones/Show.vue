<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { computed, ref, watchEffect } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import { ArrowLeftIcon, CheckBadgeIcon, XCircleIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
  postulacion: { type: Object, required: true },
  enPrimerFiltro: { type: Boolean, default: false },
  passwordTemporal: { type: String, default: '' },
})

const page = usePage()

watchEffect(() => {
  const flash = page.props.flash
  if (flash?.success) Swal.fire({ icon: 'success', title: 'Correcto', text: flash.success })
  if (flash?.error) Swal.fire({ icon: 'error', title: 'Error', text: flash.error })
})

const ESTATUS = {
  pendiente: { label: 'Pendiente', clase: 'bg-yellow-100 text-yellow-800' },
  pre_aprobada: { label: 'Pre-aprobada', clase: 'bg-blue-100 text-blue-800' },
  aprobada: { label: 'Aprobada', clase: 'bg-green-100 text-green-800' },
  no_aprobada: { label: 'No aprobada', clase: 'bg-red-100 text-red-800' },
}
const estatus = computed(() =>
  ESTATUS[props.postulacion.estatus] ?? { label: 'Desconocido', clase: 'bg-gray-100 text-gray-800' }
)

// Los botones de revisión solo aplican a la postulación abierta sin revisar
const pendiente = computed(() => props.enPrimerFiltro)

const formatearFecha = fecha => {
  if (!fecha) return '—'
  return new Date(fecha).toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const p = props.postulacion
const nombreCompleto = [p.nombre, p.apellido_paterno, p.apellido_materno].filter(Boolean).join(' ')

const datos = computed(() => [
  { etiqueta: 'Nombre', valor: nombreCompleto },
  { etiqueta: 'Género', valor: props.postulacion.genero },
  { etiqueta: 'Correo electrónico', valor: props.postulacion.correo },
  { etiqueta: 'Confirmación de correo', valor: props.postulacion.correo_confirmacion },
  { etiqueta: 'Domicilio', valor: props.postulacion.direccion },
  { etiqueta: 'Consejo de interés', valor: props.postulacion.consejo_solicitado?.nombre },
  { etiqueta: 'Perfil', valor: props.postulacion.perfil },
  { etiqueta: 'Sobre la persona', valor: props.postulacion.perfil_descripcion },
  { etiqueta: 'Fecha de postulación', valor: formatearFecha(props.postulacion.fecha_postulacion) },
  { etiqueta: 'Aviso de privacidad aceptado', valor: formatearFecha(props.postulacion.aviso_privacidad_at) },
])

// Pre-aprobar
const procesando = ref(false)

const preAprobar = async () => {
  const { isConfirmed } = await Swal.fire({
    icon: 'question',
    title: '¿Pre-aprobar esta postulación?',
    text: `${nombreCompleto} pasará a la siguiente etapa: se creará su acceso como invitado (${props.postulacion.correo}) para que cargue sus documentos.`,
    showCancelButton: true,
    confirmButtonText: 'Pre-aprobar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#1E520B',
  })
  if (!isConfirmed) return

  router.post(route('postulaciones.pre-aprobar', props.postulacion.id), {}, {
    preserveScroll: true,
    onStart: () => (procesando.value = true),
    onFinish: () => (procesando.value = false),
  })
}

// No aprobar (con comentarios)
const mostrarNoAprobar = ref(false)
const COMENTARIOS_MAX = 2000
const formNoAprobar = useForm({ comentarios_revision: '' })

const cancelarNoAprobar = () => {
  mostrarNoAprobar.value = false
  formNoAprobar.reset()
  formNoAprobar.clearErrors()
}

const noAprobar = async () => {
  if (formNoAprobar.comentarios_revision.trim().length < 10) {
    formNoAprobar.setError('comentarios_revision', 'Los comentarios deben tener al menos 10 caracteres.')
    return
  }

  const { isConfirmed } = await Swal.fire({
    icon: 'warning',
    title: '¿No aprobar esta postulación?',
    text: 'Esta acción no se puede deshacer.',
    showCancelButton: true,
    confirmButtonText: 'No aprobar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#dc2626',
  })
  if (!isConfirmed) return

  formNoAprobar.post(route('postulaciones.no-aprobar', props.postulacion.id), {
    preserveScroll: true,
    onSuccess: () => (mostrarNoAprobar.value = false),
  })
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Detalle de postulación" />

    <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8 space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <Link :href="route('postulaciones.index')"
            class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-800">
            <ArrowLeftIcon class="w-4 h-4" aria-hidden="true" />
            Regresar a postulaciones
          </Link>
          <h1 class="mt-1 text-2xl font-bold text-gray-800">{{ nombreCompleto }}</h1>
        </div>
        <span :class="['px-3 py-1 text-xs rounded-full font-semibold', estatus.clase]">
          {{ estatus.label }}
        </span>
      </div>

      <!-- Datos de la postulación -->
      <section class="bg-white shadow-sm sm:rounded-lg">
        <h2 class="px-6 py-4 border-b border-gray-100 font-semibold text-gray-800">Datos de la postulación</h2>
        <dl class="divide-y divide-gray-100">
          <div v-for="d in datos" :key="d.etiqueta" class="grid gap-1 px-6 py-3 sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm font-medium text-gray-500">{{ d.etiqueta }}</dt>
            <dd class="sm:col-span-2 text-sm text-gray-800 whitespace-pre-line break-words">{{ d.valor || '—' }}</dd>
          </div>
        </dl>
      </section>

      <!-- Resultado de la revisión -->
      <section v-if="!pendiente" class="bg-white shadow-sm sm:rounded-lg">
        <h2 class="px-6 py-4 border-b border-gray-100 font-semibold text-gray-800">Revisión</h2>
        <dl class="divide-y divide-gray-100">
          <div class="grid gap-1 px-6 py-3 sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm font-medium text-gray-500">Revisó</dt>
            <dd class="sm:col-span-2 text-sm text-gray-800">{{ postulacion.validador?.name || '—' }}</dd>
          </div>
          <div class="grid gap-1 px-6 py-3 sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm font-medium text-gray-500">Fecha</dt>
            <dd class="sm:col-span-2 text-sm text-gray-800">{{ formatearFecha(postulacion.fecha_validacion) }}</dd>
          </div>
          <div v-if="postulacion.estatus === 'pre_aprobada' && postulacion.user" class="grid gap-1 px-6 py-3 sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm font-medium text-gray-500">Acceso como invitado</dt>
            <dd class="sm:col-span-2 text-sm text-gray-800 space-y-1">
              <p>Usuario: <span class="font-medium break-all">{{ postulacion.user.email }}</span></p>
              <p>Contraseña temporal: <span class="font-mono font-medium">{{ passwordTemporal }}</span></p>
              <p class="text-xs text-gray-500">
                {{ postulacion.documentos_count
                  ? 'Ya cargó sus documentos: la postulación está en el Panel de validación.'
                  : 'Aún no carga sus documentos. Al iniciar sesión se le pedirá cambiar la contraseña.' }}
              </p>
            </dd>
          </div>
          <div v-if="postulacion.comentarios_revision" class="grid gap-1 px-6 py-3 sm:grid-cols-3 sm:gap-4">
            <dt class="text-sm font-medium text-gray-500">Comentarios</dt>
            <dd class="sm:col-span-2 text-sm text-gray-800 whitespace-pre-line break-words">{{ postulacion.comentarios_revision }}</dd>
          </div>
        </dl>
      </section>

      <!-- Acciones del primer filtro -->
      <section v-if="pendiente" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
        <div>
          <h2 class="font-semibold text-gray-800">Revisión de la postulación</h2>
          <p class="text-sm text-gray-500">Decida si la postulación avanza a la siguiente etapa.</p>
        </div>

        <div v-if="!mostrarNoAprobar" class="flex flex-col gap-3 sm:flex-row">
          <button type="button" @click="preAprobar" :disabled="procesando"
            class="inline-flex justify-center items-center gap-2 px-5 py-2 rounded-md text-white font-semibold hover:opacity-90 disabled:opacity-50"
            style="background-color:#1E520B;">
            <CheckBadgeIcon class="w-5 h-5" aria-hidden="true" />
            Pre-aprobar
          </button>
          <button type="button" @click="mostrarNoAprobar = true" :disabled="procesando"
            class="inline-flex justify-center items-center gap-2 px-5 py-2 rounded-md bg-red-600 text-white font-semibold hover:bg-red-700 disabled:opacity-50">
            <XCircleIcon class="w-5 h-5" aria-hidden="true" />
            No aprobar
          </button>
        </div>

        <form v-else @submit.prevent="noAprobar" class="space-y-3 rounded-lg border border-red-200 bg-red-50 p-4">
          <div>
            <label for="comentarios_revision" class="block text-sm font-medium text-red-900">
              Comentarios <span class="text-red-600">*</span>
            </label>
            <p class="text-xs text-red-800">Explique el motivo por el que la postulación no se aprueba.</p>
            <textarea id="comentarios_revision" v-model="formNoAprobar.comentarios_revision" rows="5"
              :maxlength="COMENTARIOS_MAX"
              :class="[
                'mt-1 block w-full rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 text-sm',
                formNoAprobar.errors.comentarios_revision ? 'border-red-500' : 'border-gray-300',
              ]"></textarea>
            <div class="mt-1 flex justify-between gap-2">
              <p v-if="formNoAprobar.errors.comentarios_revision" class="text-sm text-red-600">
                {{ formNoAprobar.errors.comentarios_revision }}
              </p>
              <span class="ml-auto text-xs text-gray-500">
                {{ formNoAprobar.comentarios_revision.length }} / {{ COMENTARIOS_MAX }}
              </span>
            </div>
          </div>
          <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button type="button" @click="cancelarNoAprobar"
              class="px-4 py-2 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
              Cancelar
            </button>
            <button type="submit" :disabled="formNoAprobar.processing"
              class="px-4 py-2 rounded-md bg-red-600 text-white font-semibold hover:bg-red-700 disabled:opacity-50">
              {{ formNoAprobar.processing ? 'Guardando…' : 'Confirmar no aprobación' }}
            </button>
          </div>
        </form>
      </section>
    </div>
  </AuthenticatedLayout>
</template>
