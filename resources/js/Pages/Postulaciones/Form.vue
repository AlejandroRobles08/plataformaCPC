<script setup>
import { computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import DragagableModal from '@/Components/DraggableModal.vue'

const props = defineProps({
  consejos: {
    type: Array,
    default: () => []
  },
  formulasOcupadas: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['close'])

const form = useForm({
  nombre: '',
  apellidos: '',
  correo: '',
  puesto: '',
  genero: '',
  direccion: '',
  consejo_id: '',
  formula: '',
  documentos: {
    ine: null,
    comprobante_domiciliario: null,
    bajo_protesta: null,
    integracion_formula: null,
    curriculum_vitae: null,
    carta_motivos: null,
    cumplimiento_normatividad: null
  }
})

const generos = [
  { value: 'mujer', label: 'Mujer' },
  { value: 'hombre', label: 'Hombre' },
  { value: 'no_binario', label: 'No binario' },
  { value: 'prefiero_autodescribirme', label: 'Prefiero autodescribirme' },
  { value: 'prefiero_no_responder', label: 'Prefiero no responder' }
]

//manejo de fórmulas disponibles
const formulasDisponibles = computed(() => {
  if (!form.consejo_id) {
    return []
  }

  const formulas = Array.from(
    { length: 15 },
    (_, index) => index + 1
  )

  const ocupacionConsejo =
    props.formulasOcupadas[form.consejo_id] ?? []

  return formulas.filter(numeroFormula => {
    const formulaEncontrada = ocupacionConsejo.find(
      item => Number(item.formula) === Number(numeroFormula)
    )

    if (!formulaEncontrada) {
      return true
    }

    return Number(formulaEncontrada.total) < 2
  })
})

//limpia la fórmula al cambiar de consejo
watch(
  () => form.consejo_id,
  () => {
    form.formula = ''
  }
)

const submit = () => {
  form.post(route('postulaciones.store'), {
    forceFormData: true,

    onSuccess: () => {
      form.reset()
      emit('close')
    }
  })
}
</script>

<template>
  <DragagableModal title="Nueva Postulación" max-width="max-w-3xl" @close="emit('close')">
      <form @submit.prevent="submit" class="space-y-6">

        <!-- Datos personales -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

          <!-- Nombre -->
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Nombre(s)
            </label>

            <input v-model="form.nombre" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />

            <div v-if="form.errors.nombre" class="text-red-500 text-sm">
              {{ form.errors.nombre }}
            </div>
          </div>

          <!-- Apellidos -->
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Apellidos
            </label>

            <input v-model="form.apellidos" type="text"
              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />

            <div v-if="form.errors.apellidos" class="text-red-500 text-sm">
              {{ form.errors.apellidos }}
            </div>
          </div>

          <!-- Género -->
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Género
            </label>
            <select v-model="form.genero" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
              <option value="">Seleccione un género</option>
              <option v-for="genero in generos" :key="genero.value" :value="genero.value">
                {{ genero.label }}
              </option>
            </select>
            <div v-if="form.errors.genero" class="text-red-500 text-sm">
              {{ form.errors.genero }}
            </div>
          </div>

          <!-- Dirección -->
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Dirección
            </label>
            <textarea v-model="form.direccion" rows="2" maxlength="500"
              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
              placeholder="Calle, número, colonia, código postal"></textarea>
            <div v-if="form.errors.direccion" class="text-red-500 text-sm">
              {{ form.errors.direccion }}
            </div>
          </div>

          <!-- Correo -->
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Correo electrónico
            </label>

            <input v-model="form.correo" type="email" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />

            <div v-if="form.errors.correo" class="text-red-500 text-sm">
              {{ form.errors.correo }}
            </div>
          </div>

          <!-- Cargo -->
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Cargo
            </label>

            <input v-model="form.puesto" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />

            <div v-if="form.errors.puesto" class="text-red-500 text-sm">
              {{ form.errors.puesto }}
            </div>
          </div>

          <!-- Consejo -->
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Consejo
            </label>

            <select v-model="form.consejo_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
              <option value="">
                Seleccione un consejo
              </option>

              <option v-for="c in props.consejos" :key="c.id" :value="c.id">
                {{ c.nombre }}
              </option>
            </select>

            <div v-if="form.errors.consejo_id" class="text-red-500 text-sm">
              {{ form.errors.consejo_id }}
            </div>
          </div>

          <!-- Fórmula -->
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Fórmula
            </label>

            <select v-model="form.formula" :disabled="!form.consejo_id"
              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm disabled:bg-gray-100">
              <option value="">
                {{
                  form.consejo_id
                    ? 'Seleccione una fórmula'
                    : 'Primero seleccione un consejo'
                }}
              </option>

              <option v-for="formula in formulasDisponibles" :key="formula" :value="formula">
                Fórmula {{ formula }}
              </option>
            </select>

            <div v-if="form.errors.formula" class="text-red-500 text-sm">
              {{ form.errors.formula }}
            </div>
          </div>

        </div>

        <!-- Expediente -->
        <div class="border-t pt-4">

          <h4 class="font-semibold text-gray-700 mb-3">
            Expediente (Todos los documentos son obligatorios en PDF)
          </h4>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- INE -->
            <div>
              <label class="text-sm">
                INE
              </label>

              <input type="file" accept="application/pdf" @change="e => form.documentos.ine = e.target.files[0]"
                class="block w-full mt-1" />

              <div v-if="form.errors['documentos.ine']" class="text-red-500 text-sm">
                {{ form.errors['documentos.ine'] }}
              </div>
            </div>

            <!-- Comprobante domiciliario -->
            <div>
              <label class="text-sm">
                Comprobante domiciliario
              </label>

              <input type="file" accept="application/pdf"
                @change="e => form.documentos.comprobante_domiciliario = e.target.files[0]" class="block w-full mt-1" />

              <div v-if="form.errors['documentos.comprobante_domiciliario']" class="text-red-500 text-sm">
                {{ form.errors['documentos.comprobante_domiciliario'] }}
              </div>
            </div>

            <!-- Bajo protesta -->
            <div>
              <label class="text-sm">
                Bajo protesta art. 170
              </label>

              <input type="file" accept="application/pdf"
                @change="e => form.documentos.bajo_protesta = e.target.files[0]" class="block w-full mt-1" />

              <div v-if="form.errors['documentos.bajo_protesta']" class="text-red-500 text-sm">
                {{ form.errors['documentos.bajo_protesta'] }}
              </div>
            </div>

            <!-- Integración de fórmula -->
            <div>
              <label class="text-sm">
                Integración de fórmula
              </label>

              <input type="file" accept="application/pdf"
                @change="e => form.documentos.integracion_formula = e.target.files[0]" class="block w-full mt-1" />

              <div v-if="form.errors['documentos.integracion_formula']" class="text-red-500 text-sm">
                {{ form.errors['documentos.integracion_formula'] }}
              </div>
            </div>

            <!-- Currículum vitae -->
            <div>
              <label class="text-sm">
                Currículum Vitae
              </label>

              <input type="file" accept="application/pdf"
                @change="e => form.documentos.curriculum_vitae = e.target.files[0]" class="block w-full mt-1" />

              <div v-if="form.errors['documentos.curriculum_vitae']" class="text-red-500 text-sm">
                {{ form.errors['documentos.curriculum_vitae'] }}
              </div>
            </div>

            <!-- Carta de motivos -->
            <div>
              <label class="text-sm">
                Carta de motivos
              </label>

              <input type="file" accept="application/pdf"
                @change="e => form.documentos.carta_motivos = e.target.files[0]" class="block w-full mt-1" />

              <div v-if="form.errors['documentos.carta_motivos']" class="text-red-500 text-sm">
                {{ form.errors['documentos.carta_motivos'] }}
              </div>
            </div>

            <!-- Cumplimiento de normatividad -->
            <div>
              <label class="text-sm">
                Cumplimiento de normatividad
              </label>

              <input type="file" accept="application/pdf"
                @change="e => form.documentos.cumplimiento_normatividad = e.target.files[0]"
                class="block w-full mt-1" />

              <div v-if="form.errors['documentos.cumplimiento_normatividad']" class="text-red-500 text-sm">
                {{ form.errors['documentos.cumplimiento_normatividad'] }}
              </div>
            </div>

          </div>
        </div>

        <!-- Botones -->
        <div class="flex justify-end space-x-2 pt-4">

          <button type="button" @click="emit('close')" class="px-4 py-2 rounded-md text-white"
            style="background-color:#C91212;">
            Cancelar
          </button>

          <button type="submit" :disabled="form.processing"
            class="px-4 py-2 bg-yellow-700 text-white rounded-md hover:bg-yellow-800 disabled:opacity-50">
            Guardar
          </button>
        </div>
      </form>
  </DragagableModal>
</template>