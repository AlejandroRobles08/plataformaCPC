<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { PlusIcon } from '@heroicons/vue/24/solid'

defineProps({
  consejos: Array,
  origen: String,
})

// Solo admin y super_admin tienen el permiso para agregar consejos
const page = usePage()
const puedeCrearConsejo = computed(() =>
  page.props.auth?.permissions?.includes('consejos.crear')
)

// Los integrantes no ven la simbología de instalado/no instalado
const esIntegrante = computed(() =>
  page.props.auth?.roles?.includes('integrante')
)

// Texto del aviso rojo según el módulo
const mensajesPendientes = {
  integrantes: 'Documentos por revisar',
  asistencias: 'Justificantes por revisar',
  legalidad: 'Solicitudes de reelección por revisar',
}

// Ícono (Material Symbols) guardado en el consejo; genérico si aún no tiene uno
const ICONO_DEFAULT = 'group_work'
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Archivo Digital" />

    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">

      <!-- TÍTULO DINÁMICO -->
      <h1 class="text-2xl font-bold mb-6 text-center">
        {{
          origen === 'asistencias'
            ? 'Asistencias y Participación'
            : origen === 'convocatorias'
              ? 'Convocatorias y sesiones'
              : origen === 'reportes'
                ? 'Reportes'
                : origen === 'legalidad'
                  ? 'Periodo en el cargo'
                  : 'Archivo Digital'
        }}
      </h1>

      <!-- SIMBOLOGÍA -->
      <div v-if="consejos.length && !esIntegrante" class="flex justify-center gap-6 mb-4 text-sm text-gray-600">
        <span class="flex items-center gap-2">
          <span class="w-4 h-4 rounded bg-[#7D2447]"></span>
          Instalado
        </span>
        <span class="flex items-center gap-2">
          <span class="w-4 h-4 rounded bg-gray-600"></span>
          No instalado
        </span>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <Link
          v-for="consejo in consejos"
          :key="consejo.id"
          :href="
            origen === 'convocatorias'
              ? route('convocatorias.index', { consejo: consejo.id })
              : origen === 'asistencias'
                ? route('asistencias.index', { consejo: consejo.id })
                : origen === 'reportes'
                  ? route('reportes.consejo', { consejo: consejo.id })
                  : origen === 'legalidad'
                    ? route('legalidad.index', { consejo: consejo.id })
                    : route('consejos.integrantes', consejo.id)
          "
          :class="consejo.instalado
            ? 'bg-[#7D2447] hover:bg-[#5E1B35]'
            : 'bg-gray-600 hover:bg-black'"
          class="relative p-4 shadow rounded-lg flex flex-col items-center justify-center
                 font-semibold text-center text-white transition">

          <!-- Aviso de pendientes por revisar dentro del consejo -->
          <span v-if="consejo.pendientes"
            :title="mensajesPendientes[origen]"
            class="absolute -top-2 -right-2 min-w-6 h-6 px-1 flex items-center justify-center
                   rounded-full bg-red-600 text-white text-xs font-bold ring-2 ring-white">
            <span class="absolute inset-0 animate-ping rounded-full bg-red-600 opacity-60"></span>
            <!-- Siempre 1: solo avisa que hay algo; el detalle está dentro de cada módulo -->
            <span class="relative">1</span>
          </span>

          <span class="material-symbols-outlined text-white text-5xl mb-2">
            {{ consejo.icono || ICONO_DEFAULT }}
          </span>

          {{ consejo.nombre }}
        </Link>

        <!-- AGREGAR CONSEJO -->
        <Link v-if="puedeCrearConsejo" :href="route('consejos.create', { origen })"
          class="p-4 rounded-lg flex flex-col items-center justify-center font-semibold text-center
                 border-2 border-dashed border-[#7D2447] text-[#7D2447] bg-white hover:bg-[#7D2447]/10 transition">
          <PlusIcon class="w-10 h-10 mb-2" />
          Agregar consejo
        </Link>
      </div>

      <p v-if="!consejos.length" class="text-center text-gray-500">
        Aún no estás asignado a ningún consejo. Si crees que es un error, comunícate con la administración.
      </p>

    </div>
  </AuthenticatedLayout>
</template>
