<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const props = defineProps({
  origen: {
    type: String,
    default: 'integrantes'
  }
})

// Vista de tarjetas a la que se regresa según el módulo de origen
const rutasOrigen = {
  integrantes: 'consejos.index',
  asistencias: 'consejos.asistencias',
  convocatorias: 'consejos.convocatorias',
  reportes: 'consejos.reportes',
  legalidad: 'consejos.legalidad',
}

const form = useForm({
  nombre: '',
  descripcion: '',
  origen: props.origen,
})

function submit() {
  form.post(route('consejos.store'), {
    onSuccess: () => {
      Swal.fire({
        icon: 'success',
        title: 'Consejo agregado',
        text: 'El consejo se instalará automáticamente al completar 10 fórmulas.',
        confirmButtonColor: '#7D2447'
      })
    }
  })
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Instalación de consejo" />

    <div class="mt-2 mb-4">
      <Link :href="route(rutasOrigen[origen] ?? 'consejos.index')" class="text-gray-600 hover:underline">
        ← Volver a Consejos de Participación Ciudadana
      </Link>
    </div>

    <div class="max-w-2xl mx-auto p-4 sm:p-8 bg-white shadow sm:rounded-lg">
      <h1 class="text-2xl font-bold mb-2 text-center">
        Agregar consejo
      </h1>
      <p class="text-sm text-gray-500 text-center mb-6">
        El consejo se registra como <span class="font-semibold">No instalado</span> y se instala
        automáticamente cuando completa 10 fórmulas (20 integrantes).
      </p>

      <form @submit.prevent="submit" class="space-y-4">
        <!-- Nombre -->
        <div>
          <label class="block text-sm font-medium text-gray-700">
            Nombre del consejo
          </label>
          <input v-model="form.nombre" type="text" maxlength="255" required
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
          <p v-if="form.errors.nombre" class="text-red-500 text-sm">
            {{ form.errors.nombre }}
          </p>
        </div>

        <!-- Descripción -->
        <div>
          <label class="block text-sm font-medium text-gray-700">
            Descripción (opcional)
          </label>
          <textarea v-model="form.descripcion" rows="4" maxlength="255"
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></textarea>
          <p class="text-xs text-gray-500 text-right">
            {{ form.descripcion.length }}/255
          </p>
          <p v-if="form.errors.descripcion" class="text-red-500 text-sm">
            {{ form.errors.descripcion }}
          </p>
        </div>

        <!-- Botones -->
        <div class="flex justify-end space-x-2 pt-4">
          <Link :href="route(rutasOrigen[origen] ?? 'consejos.index')"
            class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
            Cancelar
          </Link>
          <button type="submit" :disabled="form.processing"
            class="px-4 py-2 bg-[#7D2447] text-white rounded-md hover:bg-[#5E1B35] disabled:opacity-50">
            Guardar
          </button>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>
