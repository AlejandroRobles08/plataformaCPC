<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link } from '@inertiajs/vue3'
import { formatearFecha } from '@/Components/Reportes/reportes.js'

//props para recibir datos desde el controlador
defineProps({
  consejo: Object,
  bajas: Array,
})

function obtenerNombreArchivo(ruta) {
  if (!ruta) return '-'
  return ruta.split('/').pop()
}

function obtenerUrlPublica(ruta) {
  if (!ruta) return '#'
  return `/storage/${ruta}`
}
</script>

<template>
  <AuthenticatedLayout>
    <div class="mt-2 mb-4">
      <Link :href="route('reportes.consejo', consejo.id)" class="text-gray-600 hover:underline">
        ← Volver a Reportes
      </Link>
    </div>
    <div class="p-6 bg-white rounded-lg shadow-md">

      <h1 class="text-2xl font-bold mb-6">
        Bajas de integrantes del Consejo de Participación Ciudadana de {{ consejo.nombre }}
      </h1>

      <div class="overflow-x-auto">
        <table class="min-w-full border text-sm">
          <thead class="bg-gray-100">
            <tr>
              <th class="p-2 text-left">Integrante</th>
              <th class="p-2 text-left">Motivo</th>
              <th class="p-2 text-left">Fecha</th>
              <th class="p-2 text-left">Evidencia</th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="!bajas?.length">
              <td colspan="4" class="p-4 text-center text-gray-500">
                No hay integrantes dados de baja en este consejo.
              </td>
            </tr>

            <tr v-for="baja in bajas" :key="baja.id" class="border-t">
              <td class="p-2">{{ baja.nombre }} {{ baja.apellido }}</td>
              <td class="p-2 capitalize">{{ baja.motivo }}</td>
              <td class="p-2">{{ formatearFecha(baja.fecha_baja) }}</td>
              <td class="p-2">
                <a v-if="baja.evidencia_pdf" :href="obtenerUrlPublica(baja.evidencia_pdf)" target="_blank"
                  class="text-red-700 underline">
                  {{ obtenerNombreArchivo(baja.evidencia_pdf) }}
                </a>
                <span v-else class="text-gray-500">Sin archivo</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
