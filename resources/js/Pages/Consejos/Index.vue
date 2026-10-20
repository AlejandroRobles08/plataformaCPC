<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { defineComponent, h } from 'vue'

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
  AcademicCapIcon,
} from '@heroicons/vue/24/solid'

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
          class="p-4 shadow rounded-lg flex flex-col items-center justify-center
                 font-semibold text-center text-white bg-gray-600 hover:bg-black transition">

          <component
            :is="icons[normalize(consejo.nombre)] || AcademicCapIcon"
            class="w-10 h-10 text-white mb-2"/>

          {{ consejo.nombre }}
        </Link>
      </div>

    </div>
  </AuthenticatedLayout>
</template>
