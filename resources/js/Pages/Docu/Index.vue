<script setup>

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, useForm, usePage, router } from '@inertiajs/vue3'
import {EyeIcon, CloudArrowUpIcon, ArrowDownTrayIcon, PaperClipIcon, BellIcon, CheckCircleIcon, XCircleIcon,
  ClockIcon, HandThumbUpIcon, HandThumbDownIcon, } from '@heroicons/vue/24/solid'
import { ref, watch, computed } from 'vue'
import Swal from 'sweetalert2'


const props = defineProps({
  integrante: Object,
})
const page = usePage()

// Roles del usuario autenticado
const roles = computed(() => {
  return page.props.roles ?? page.props.auth?.roles ?? []
})
const isReviewer = computed(() =>
  roles.value.includes('admin') ||
  roles.value.includes('super_admin')
)
const isIntegrante = computed(() =>
  roles.value.includes('integrante')
)

//Sweet Alert para mensajes flash
watch(
  () => page.props.flash?.success,
  (success) => {
    if (success) {
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: success,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      })
    }
  },
  { immediate: true })

// Formulario de documentos
const form = useForm({
  ine: null,
  comprobante_domiciliario: null,
  bajo_protesta_art_170: null,
  integracion_formula: null,
  curriculum_vitae: null,
  carta_motivos: null,
  cumplimiento_normatividad: null, })
  
const selectedFiles = ref({})

const submitForm = () => {
  form.post(route('docu.store', props.integrante.id), {
    forceFormData: true,

    onSuccess: () => {
      form.reset()
      selectedFiles.value = {}

      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Documentos guardados correctamente',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      })
    },
  })
}

// Lista de documentos
const documentos = [
  {
    key: 'ine',
    label: 'INE',
  },
  {
    key: 'comprobante_domiciliario',
    label: 'Comprobante domiciliario',
  },
  {
    key: 'bajo_protesta_art_170',
    label: 'Bajo protesta art. 170',
  },
  {
    key: 'integracion_formula',
    label: 'Integración de fórmula',
  },
  {
    key: 'curriculum_vitae',
    label: 'Currículum Vitae',
  },
  {
    key: 'carta_motivos',
    label: 'Carta de motivos',
  },
  {
    key: 'cumplimiento_normatividad',
    label: 'Cumplimiento de normatividad',
  },
]

// Obtener documento existente
const getDoc = (tipo) =>
  props.integrante.documentos?.find(
    (documento) => documento.tipo === tipo
  )

// Nombre limpio del documento
const getCleanFileName = (doc) => {
  if (!doc || !doc.tipo) return ''
  const item = documentos.find(
    documento => documento.key === doc.tipo
  )
  if (!item) {
    return 'Documento.pdf'
  }
  return `${item.label}.pdf`
}

// URL pública del documento
const getPublicUrl = (ruta) =>
  `/storage/${ruta.replace('public/', '')}`

// Manejar selección de archivo
const handleFileSelect = (event, key) => {
  const file = event.target.files[0]
  if (file) {
    form[key] = file
    selectedFiles.value[key] = file.name
  }
}

// Texto del estado
const getStatusLabel = (doc) => {
  if (!doc) {
    return 'No cargado'
  }
  switch (doc.estatus) {
    case 'aprobado':
      return 'Aprobado'
    case 'rechazado':
      return 'Rechazado'
    case 'pendiente':
    default:
      return 'Pendiente de revisión'
  }
}

// Aprobar documento
const aprobarDocumento = (documento) => {
  Swal.fire({
    title: '¿Aprobar documento?',
    text: 'El documento será marcado como aprobado.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, aprobar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#15803d',
  }).then((result) => {
    if (result.isConfirmed) {
      router.patch(
        route('docu.aprobar', documento.id),
        {},
        {
          preserveScroll: true,
        }
      )
    }
  })
}

// Rechazar documento
const rechazarDocumento = async (documento) => {
  const result = await Swal.fire({
    title: 'Rechazar documento',
    text: 'Indica la observación para el integrante.',
    input: 'textarea',
    inputLabel: 'Observación',
    inputPlaceholder:
      'Escribe el motivo por el cual el documento fue rechazado...',
    inputAttributes: {
      'aria-label': 'Observación del documento',
    },
    showCancelButton: true,
    confirmButtonText: 'Rechazar documento',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#dc2626',

    inputValidator: (value) => {
      if (!value) {
        return 'Debes escribir una observación.'
      }
    },
  })
  if (result.isConfirmed) {
    router.patch(
      route('docu.rechazar', documento.id),
      {
        observacion: result.value,
      },
      {
        preserveScroll: true,
      }
    )
  }
}
</script>


<template>
  <AuthenticatedLayout>
    <!-- Botón regresar -->
    <div class="mb-4">
      <!-- Admin / Super Admin -->
      <Link v-if="isReviewer" :href="route('consejos.integrantes', integrante.consejo_id)"
        class="inline-flex items-center px-3 
        py-2 text-sm text-gray-800 transition bg-gray-200 rounded hover:bg-gray-300">
        ← Volver a Integrantes
      </Link>
      <!-- Integrante -->
      <Link v-else :href="route('dashboard')"
        class="inline-flex items-center px-3 py-2 text-sm text-gray-800 
        transition bg-gray-200 rounded hover:bg-gray-300">
        ← Volver al inicio
      </Link>
    </div>

    <div class="p-6">
      <h1 class="mb-2 text-2xl font-bold">
        Documentos de
        {{ integrante.nombre }}
        {{ integrante.apellido }}
      </h1>
      <p class="mb-6 text-sm text-gray-500">
        Revisa el estado de cada documento y consulta las observaciones
        cuando sea necesario.
      </p>
      <!-- Formulario -->
      <form @submit.prevent="submitForm" class="space-y-4">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div v-for="doc in documentos" :key="doc.key"
            class="p-4 transition bg-gray-50 border rounded-lg hover:bg-gray-100">
            <!-- Nombre -->
            <label class="block mb-3 text-sm font-semibold">
              {{ doc.label }}
            </label>
            <!-- Documento existente -->
            <div v-if="getDoc(doc.key)">
              <!-- Nombre archivo -->
              <p class="flex items-center gap-1 mb-3 text-sm font-medium text-gray-700">
                <PaperClipIcon class="w-4 h-4" />
                {{ getCleanFileName(getDoc(doc.key)) }}
              </p>
              <!-- Estado pendiente -->
              <div
                v-if="getDoc(doc.key).estatus === 'pendiente'"
                class="flex items-center gap-2 mb-3 text-sm text-yellow-700">
                <ClockIcon class="w-5 h-5" />
                Pendiente de revisión
              </div>
              
              <!-- Estado rechazado -->
              <div v-else-if="getDoc(doc.key).estatus === 'rechazado'"
                class="flex items-center gap-2 mb-3 text-sm text-red-700">
                <XCircleIcon class="w-5 h-5" />
                Documento rechazado
              </div>

              <!-- Documento aprobado -->
              <div v-if="getDoc(doc.key).estatus === 'aprobado'"
                class="flex items-center gap-2 p-3 mb-4 text-sm font-medium text-green-700
                bg-green-50 border border-green-200 rounded-lg">
                <CheckCircleIcon class="w-5 h-5 text-green-600" />
              <div>
                <p class="font-semibold">
                  Documento validado
                </p>
                <!-- 
                <p class="text-xs font-normal text-green-600">
                  Este documento ha sido revisado y aprobado por el administrador.
                </p> -->
              </div>
            </div>
              <!-- Acciones generales -->
              <div class="flex flex-wrap gap-3 mb-4">
                <a :href="getPublicUrl(getDoc(doc.key).archivo)"
                  target="_blank" class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800">
                  <EyeIcon class="w-4 h-4 mr-1" />
                  Ver
                </a>

                <a :href="route('docu.download', getDoc(doc.key).id)"
                  class="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-800">
                  <ArrowDownTrayIcon class="w-4 h-4 mr-1" />
                  Descargar
                </a>
              </div>

              <!-- Observación -->
              <div v-if=" getDoc(doc.key).estatus === 'rechazado' && getDoc(doc.key).observacion
                "class="p-3 mb-4 border border-red-200 rounded-lg bg-red-50">
                <p class="mb-1 text-sm font-semibold text-red-700">
                  Observación del administrador:
                </p>
                <p class="text-sm text-red-600">
                  {{ getDoc(doc.key).observacion }}
                </p>
              </div>

              <!-- Acciones para alidar (rechazar/aprobar) -->
              <div v-if="isReviewer" class="flex flex-wrap gap-3">
                <!-- Documento aprobado -->
                 <div v-if="getDoc(doc.key).estatus === 'aprobado'" 
                 class="flex items-center gap-2 mb-3 text-sm text-green-700">
                  <CheckCircleIcon class="w-5 h-5" />
                  Documento validado y aprobado
                 </div>

                <!-- Aprobar -->
                <button v-else type="button" @click="aprobarDocumento(getDoc(doc.key))"
                  class="inline-flex items-center px-3 py-2 text-sm text-white transition bg-green-600 
                  rounded hover:bg-green-700">
                  <HandThumbUpIcon class="w-4 h-4 mr-1" />
                  Aprobar
                </button>

                <!-- Rechazar -->
                <button v-if="getDoc(doc.key).estatus !== 'aprobado'"
                  type="button" @click="rechazarDocumento(getDoc(doc.key))"
                  class="inline-flex items-center px-3 py-2 text-sm text-white 
                  transition bg-red-600 rounded hover:bg-red-700">
                  <HandThumbDownIcon class="w-4 h-4 mr-1" />
                  No aprobar
                </button>
              </div>

              <!-- Reemplazar documento -->
              <div v-if=" isIntegrante && getDoc(doc.key).estatus === 'rechazado'
                "class="mt-4">
                <label
                  class="inline-flex items-center px-3 py-2 text-xs transition bg-white 
                  border border-gray-300 rounded cursor-pointer hover:bg-gray-200">
                  <PaperClipIcon class="w-4 h-4 mr-1" />
                  Reemplazar documento
                  <input
                    type="file" accept="application/pdf" class="hidden"
                    @change="handleFileSelect($event, doc.key)"/>
                </label>

                <span v-if="selectedFiles[doc.key]" class="block mt-2 text-xs text-gray-700">
                  {{ selectedFiles[doc.key] }}
                </span>
              </div>
            </div>

            <!-- Documento no cargado -->
            <div v-else>
              <div class="flex items-center gap-2 text-sm text-gray-500">
                <BellIcon class="w-5 h-5 text-yellow-500" />
                Aún no se ha subido
              </div>

              <!-- Solo integrante puede cargar -->
              <div v-if="isIntegrante" class="mt-4">

                <label
                  class="inline-flex items-center px-3 py-2 text-xs transition bg-white border border-gray-300 
                  rounded cursor-pointer hover:bg-gray-200">
                  <PaperClipIcon class="w-4 h-4 mr-1 text-gray-600" />
                  Cargar documento
                  <input type="file" accept="application/pdf" class="hidden"
                    @change="handleFileSelect($event, doc.key)"/>
                </label>

                <span
                  v-if="selectedFiles[doc.key]" class="block mt-2 text-xs text-gray-700">
                  {{ selectedFiles[doc.key] }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Guardar documentos -->
        <div v-if="isIntegrante" class="flex justify-end mt-6">
          <button type="submit" :disabled="form.processing" class="flex items-center px-4 py-2 
            text-white bg-green-600 rounded hover:bg-green-700 disabled:opacity-50">
            <CloudArrowUpIcon class="w-5 h-5 mr-1" />
            {{
              form.processing
                ? 'Guardando...'
                : 'Guardar documentos'
            }}
          </button>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>