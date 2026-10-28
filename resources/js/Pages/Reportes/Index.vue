<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link } from '@inertiajs/vue3'
import {
  CalendarDaysIcon,
  ClipboardDocumentCheckIcon,
  DocumentTextIcon,
  UserMinusIcon,
} from '@heroicons/vue/24/solid'

//props para recibir datos desde el controlador
const props = defineProps({
  consejo: Object,
  totales: Object,
  hayDatos: Boolean,
})

// reportes disponibles para el consejo
const reportes = [
  {
    ruta: 'reportes.sesiones',
    titulo: 'Sesiones',
    descripcion: 'Convocatorias y sesiones realizadas por mes y tipo de sesión.',
    total: props.totales.sesiones,
    etiqueta: 'convocatorias',
    icono: CalendarDaysIcon,
  },
  {
    ruta: 'reportes.asistencias',
    titulo: 'Asistencias',
    descripcion: 'Asistencias, inasistencias e inasistencias justificadas por integrante.',
    total: props.totales.asistencias,
    etiqueta: 'registros',
    icono: ClipboardDocumentCheckIcon,
  },
  {
    ruta: 'reportes.documentos',
    titulo: 'Documentos',
    descripcion: 'Checklist de documentos entregados por cada integrante.',
    total: props.totales.documentos,
    etiqueta: 'integrantes',
    icono: DocumentTextIcon,
  },
  {
    ruta: 'reportes.bajas',
    titulo: 'Bajas de integrantes',
    descripcion: 'Integrantes dados de baja, motivo, fecha y evidencia.',
    total: props.totales.bajas,
    etiqueta: 'bajas',
    icono: UserMinusIcon,
  },
]
</script>

<template>
  <AuthenticatedLayout>
    <div class="mt-2 mb-4">
      <Link :href="route('consejos.reportes')" class="text-gray-600 hover:underline">
        ← Volver a Consejos de Participación Ciudadana
      </Link>
    </div>
    <div class="p-6 bg-white rounded-lg shadow-md">

      <h1 class="text-2xl font-bold mb-6">
        Reportes del Consejo de Participación Ciudadana de {{ consejo.nombre }}
      </h1>

      <div v-if="!hayDatos" class="text-center text-gray-500 p-10 border rounded-lg bg-gray-50">
        No hay información disponible para generar reportes de este consejo.
      </div>

      <div v-else>
        <p class="text-sm text-gray-500 mb-4">
          Selecciona el tipo de reporte que deseas consultar.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Link v-for="reporte in reportes" :key="reporte.ruta"
            :href="route(reporte.ruta, consejo.id)"
            class="flex items-start gap-4 p-5 border border-gray-200 rounded-lg hover:border-gray-400 hover:shadow transition">
            <div class="p-3 rounded-lg bg-gray-600 text-white">
              <component :is="reporte.icono" class="w-6 h-6" />
            </div>
            <div>
              <h2 class="text-lg font-semibold">{{ reporte.titulo }}</h2>
              <p class="text-sm text-gray-500 mt-1">{{ reporte.descripcion }}</p>
              <p class="text-sm font-medium text-gray-700 mt-2">
                {{ reporte.total }} {{ reporte.etiqueta }}
              </p>
            </div>
          </Link>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
