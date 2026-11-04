<script setup>
// Formulario para que el admin cree un usuario con contraseña temporal.
// - rol "invitado": solo nombre y correo (Postulaciones).
// - rol "integrante": además consejo, fórmula y cargo, para dar de alta en la plataforma
//   a quien ya forma parte de un consejo (Archivo digital).
import { computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import DraggableModal from '@/Components/DraggableModal.vue'

const props = defineProps({
  rol: { type: String, default: 'invitado' },
  // Nombre de la ruta a la que se envía el formulario
  ruta: { type: String, required: true },
  titulo: { type: String, default: 'Nuevo usuario' },
  // Solo para rol integrante
  consejos: { type: Array, default: () => [] },
  consejoId: { type: Number, default: null },
  // Lugares ocupados por fórmula: { consejo_id: { formula: total } }
  ocupacion: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['close', 'created'])

// Deben coincidir con Integrante::MAX_FORMULAS, INTEGRANTES_POR_FORMULA y CARGOS
const MAX_FORMULAS = 15
const INTEGRANTES_POR_FORMULA = 2
const CARGOS = ['Persona Consejera Titular', 'Persona Consejera Suplente']

const esIntegrante = computed(() => props.rol === 'integrante')

const form = useForm({
  name: '',
  email: '',
  ...(esIntegrante.value ? { consejo_id: props.consejoId ?? '', formula: '', cargo: '' } : {}),
})

// Fórmulas del consejo elegido con su ocupación; las llenas se muestran deshabilitadas
const formulas = computed(() => {
  const ocupadas = props.ocupacion?.[form.consejo_id] ?? {}
  return Array.from({ length: MAX_FORMULAS }, (_, i) => {
    const numero = i + 1
    const total = ocupadas[numero] ?? 0
    return { numero, total, llena: total >= INTEGRANTES_POR_FORMULA }
  })
})

// Al cambiar de consejo, la fórmula elegida deja de aplicar
watch(() => form.consejo_id, () => { if (esIntegrante.value) form.formula = '' })

const cerrar = () => {
  form.reset()
  form.clearErrors()
  emit('close')
}

const submit = () => {
  form.post(route(props.ruta), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      emit('created')
    },
  })
}
</script>

<template>
  <DraggableModal :title="titulo" max-width="max-w-md" @close="cerrar">
    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700">Nombre</label>
        <input v-model="form.name" type="text" required
          class="mt-1 w-full border-gray-300 rounded-md shadow-sm" />
        <p v-if="form.errors.name" class="text-red-600 text-sm mt-1">{{ form.errors.name }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Correo</label>
        <input v-model="form.email" type="email" required
          class="mt-1 w-full border-gray-300 rounded-md shadow-sm" />
        <p v-if="form.errors.email" class="text-red-600 text-sm mt-1">{{ form.errors.email }}</p>
      </div>

      <template v-if="esIntegrante">
        <div>
          <label class="block text-sm font-medium text-gray-700">Consejo</label>
          <select v-model="form.consejo_id" required class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
            <option value="" disabled>Selecciona un consejo</option>
            <option v-for="consejo in consejos" :key="consejo.id" :value="consejo.id">
              {{ consejo.nombre }}
            </option>
          </select>
          <p v-if="form.errors.consejo_id" class="text-red-600 text-sm mt-1">{{ form.errors.consejo_id }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Fórmula</label>
          <select v-model="form.formula" required :disabled="!form.consejo_id"
            class="mt-1 w-full border-gray-300 rounded-md shadow-sm disabled:bg-gray-100">
            <option value="" disabled>Selecciona una fórmula</option>
            <option v-for="f in formulas" :key="f.numero" :value="f.numero" :disabled="f.llena">
              Fórmula {{ f.numero }} ({{ f.total }}/{{ INTEGRANTES_POR_FORMULA }}){{ f.llena ? ' - llena' : '' }}
            </option>
          </select>
          <p v-if="form.errors.formula" class="text-red-600 text-sm mt-1">{{ form.errors.formula }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Cargo</label>
          <select v-model="form.cargo" required class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
            <option value="" disabled>Selecciona un cargo</option>
            <option v-for="cargo in CARGOS" :key="cargo" :value="cargo">{{ cargo }}</option>
          </select>
          <p v-if="form.errors.cargo" class="text-red-600 text-sm mt-1">{{ form.errors.cargo }}</p>
        </div>
      </template>

      <p class="text-xs text-gray-500">
        Se generará una contraseña temporal que el usuario deberá cambiar al ingresar.
      </p>

      <div class="flex justify-end gap-2">
        <button type="button" @click="cerrar"
          class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
          Cancelar
        </button>
        <button type="submit" :disabled="form.processing"
          class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50">
          Crear
        </button>
      </div>
    </form>
  </DraggableModal>
</template>
