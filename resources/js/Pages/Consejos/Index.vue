<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed, defineComponent, h } from 'vue'

defineProps({
  consejos: Array,
  origen: String,
})

//Iconos importados desde Google Fonts
const PetsIcon = defineComponent({
  name: 'PetsIcon',
  render() {
    return h(
      'span',
      { class: 'material-icons text-white text-5xl' },
      'pets'
    )
  }
})

const AccesibleIcon = defineComponent({
  name: 'AccesibleIcon',
  render() {
    return h(
      'span',
      { class: 'material-symbols-outlined text-white text-5xl' },
      'accessible_forward'
    )
  }
})

const LocalPoliceIcon = defineComponent({
  name: 'LocalPoliceIcon',
  render() {
    return h(
      'span',
      { class: 'material-symbols-outlined text-white text-5xl' },
      'local_police'
    )
  }
})

const PaletteIcon = defineComponent({
  name: 'PaletteIcon',
  render() {
    return h(
      'span',
      { class: 'material-symbols-outlined text-white text-5xl' },
      'palette'
    )
  }
})

const FemaleIcon = defineComponent({
  name: 'FemaleIcon',
  render() {
    return h(
      'span',
      { class: 'material-symbols-outlined text-white text-5xl' },
      'female'
    )
  }
})

import {
  GlobeAmericasIcon,
  UsersIcon,
  MusicalNoteIcon,
  HeartIcon,
  BuildingOfficeIcon,
  WrenchScrewdriverIcon,
  PlusIcon,
} from '@heroicons/vue/24/solid'

// Solo admin y super_admin tienen el permiso para agregar consejos
const page = usePage()
const puedeCrearConsejo = computed(() =>
  page.props.auth?.permissions?.includes('consejos.crear')
)

// Los integrantes no ven la simbología de instalado/no instalado
const esIntegrante = computed(() =>
  page.props.auth?.roles?.includes('integrante')
)

// colapsa espacios y saltos de línea para que nombres largos coincidan con su llave
const normalize = (text) =>
  text?.replace(/\s+/g, ' ').trim().toLowerCase()

// en el mismo orden (por ID) que ConsejoSeeder
const icons = {
  'discapacidad': AccesibleIcon,
  'bienestar animal': PetsIcon,
  'centro histórico y patrimonio edificado': BuildingOfficeIcon,
  'ecología y medio ambiente': GlobeAmericasIcon,
  'seguridad pública': LocalPoliceIcon,
  'salud': HeartIcon,
  'impulso a las artesanías': PaletteIcon,
  'niñez y adolescencia': UsersIcon,
  'cultura': MusicalNoteIcon,
  'obras y servicios públicos': WrenchScrewdriverIcon,
  'igualdad de género, y prevención, atención y erradicación de la violencia contra la mujer.': FemaleIcon,
}
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
          :class="consejo.instalado || esIntegrante
            ? 'bg-[#7D2447] hover:bg-[#5E1B35]'
            : 'bg-gray-600 hover:bg-black'"
          class="p-4 shadow rounded-lg flex flex-col items-center justify-center
                 font-semibold text-center text-white transition">

          <!-- los consejos agregados desde la plataforma aún no tienen ícono -->
          <component v-if="icons[normalize(consejo.nombre)]"
            :is="icons[normalize(consejo.nombre)]"
            class="w-10 h-10 text-white mb-2"/>

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
