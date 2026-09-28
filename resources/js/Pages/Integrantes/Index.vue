<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import DraggableModal from '@/Components/DraggableModal.vue'
import { ref, computed } from 'vue'
import { useForm, Link, usePage } from '@inertiajs/vue3'
import { FolderOpenIcon } from '@heroicons/vue/24/solid'
import { PencilIcon } from '@heroicons/vue/24/outline'
import Form from './Form.vue'
import Swal from 'sweetalert2'

const props = defineProps({
  consejo: Object,
  integrantes: Array
})

const page = usePage()
const puedeEditarIntegrante = computed(()=> { 
const esSuperAdmin = 
  page.props.auth.roles?.includes('super_admin')  
const tienePermiso =
  page.props.auth.permissions?.includes('usuarios.editar')  
  return esSuperAdmin || tienePermiso
})

const showForm = ref(false)
const editingId = ref(null)

//editar descripción del consejo
const showDescripcionModal = ref(false)

const descripcionForm = useForm({
  descripcion: props.consejo.descripcion ?? ''
})

function openDescripcionModal() {
  descripcionForm.descripcion = props.consejo.descripcion ?? ''
  showDescripcionModal.value = true
}

function submitDescripcion() {
  descripcionForm.patch(
    route('consejos.descripcion.update', props.consejo.id),
    {
      onSuccess: () => {
        showDescripcionModal.value = false
      }
    }
  )
}

//form para agregar/editar integrantes
const form = useForm({
  nombre: '',
  apellido: '',
  puesto: '',
  correo: '',
  genero: '',
  direccion: '',
  discapacidad: false,
  discapacidad_tipo: '',
  consejo_id: props.consejo.id,
  formula: ''
})

function openForm() {
  editingId.value = null
  form.reset()
  form.discapacidad = false
  form.discapacidad_tipo = ''
  showForm.value = true
}

function editIntegrante(integrante) {

  editingId.value = integrante.id

  form.nombre = integrante.nombre
  form.apellido = integrante.apellido
  form.puesto = integrante.puesto
  form.correo = integrante.correo
  form.genero = integrante.genero
  form.direccion = integrante.direccion
  form.discapacidad = integrante.discapacidad === 'si'
  form.discapacidad_tipo = integrante.discapacidad_tipo ?? ''
  form.consejo_id = integrante.consejo_id
  form.formula = integrante.formula

  showForm.value = true
}

function submitForm() {
  form.discapacidad = form.discapacidad ? 'si' : 'no'
  if (editingId.value) {
    form.put(route('integrantes.update', editingId.value), {
      onSuccess: () => {
        form.reset()
        showForm.value = false
        Swal.fire({
          icon: 'success',
          title: 'Integrante actualizado',
          text: 'Los datos del integrante se actualizaron correctamente.',
          confirmButtonColor: '#16a34a'
        })
      }
    })
  } else {
    form.post(route('integrantes.store'), {
      onSuccess: () => {
        form.reset()
        showForm.value = false
        Swal.fire({
          icon: 'success',
          title: 'Integrante agregado',
          text: 'El integrante se registró correctamente.',
          confirmButtonColor: '#16a34a'
        })
      }
    })
  }
}

// BAJA / ELIMINACIÓN
const showDeleteModal = ref(false)
const integranteAEliminar = ref(null)

const bajaForm = useForm({
  motivo: '',
  fecha_baja: '',
  evidencia_pdf: null,
})

const esErrorRegistro = computed(() => bajaForm.motivo === 'error_registro')

function solicitarEliminacion(id) {

  integranteAEliminar.value = id
  bajaForm.reset()
  showDeleteModal.value = true
}

function confirmarEliminacion() {
  if (bajaForm.motivo === 'error_registro') {
    bajaForm.post(route('integrantes.baja', integranteAEliminar.value), {

      onSuccess: () => {
        showDeleteModal.value = false
        integranteAEliminar.value = null

        Swal.fire({
          icon: 'success',
          title: 'Integrante eliminado',
          text: 'El integrante se eliminó por error de registro.',
          confirmButtonColor: '#16a34a'
        })
      }
    })
    return
  }

  if (!bajaForm.fecha_baja || !bajaForm.evidencia_pdf) return
  bajaForm.post(route('integrantes.baja', integranteAEliminar.value), {
    forceFormData: true,
   onSuccess: () => {
      showDeleteModal.value = false
      integranteAEliminar.value = null
      Swal.fire({
        icon: 'success',
        title: 'Baja registrada',
        text: 'La baja del integrante se registró correctamente.',
        confirmButtonColor: '#16a34a'
      })
    }
  })
}

//agupar integrantes por fórmula
const formulas = computed(() => { 

 const grouped = {} 
  props.integrantes.forEach(i => { 
    const key = i.formula ?? 0 
    if (!grouped[key]) {
      grouped[key] = []
    } 
    grouped[key].push(i) 
  })
  return Object.entries(grouped)
      .sort((a,b) => Number(a[0]) - Number(b[0])) 
})

//semaforización de documentos
const getSemaforoClase = (integrante) => {
  const total = integrante?.documentos?.length || 0
  if (total === 0) return 'bg-red-500'
  if (total < 7) return 'bg-yellow-400'
  return 'bg-green-500'
}
</script>

<template>
  <AuthenticatedLayout>
    <div class="mt-2 mb-4">
      <Link :href="route('consejos.index', consejo.id)" class="text-gray-600 hover:underline">
        ← Volver a Consejos de Participación Ciudadana
      </Link>
    </div>
    <div class="p-6">
      <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">
          Integrantes del Consejo de Participación Ciudadana de {{ consejo.nombre }}
        </h1>
        <button v-if="puedeEditarIntegrante" @click="openForm" class="px-4 py-2 bg-black text-white rounded hover:bg-gray-500">
          + Agregar Integrante
        </button>
      </div>

      <!-- DESCRIPCIÓN CON BOTÓN MINIMALISTA -->
      <div class="flex items-start gap-2 mb-6">
        <p class="text-gray-700">
          {{ consejo.descripcion }}
        </p>
        <button v-if="puedeEditarIntegrante" @click="openDescripcionModal" class="text-gray-400 hover:text-gray-600 transition"
          title="Editar descripción">
          <PencilIcon class="w-4 h-4" />
        </button>
      </div>

      <!-- TABLA -->
      <div v-if="formulas.length" class="overflow-x-auto">
        <table class="min-w-full border border-gray-300 rounded-lg">
          <thead class="bg-gray-100">
            <tr>
              <th class="px-4 py-2 border"># Fórmula</th>
              <th class="px-4 py-2 border">Personas integrantes</th>
              <th class="px-4 py-2 border">Cargo</th>
              <th class="px-4 py-2 border">Acciones</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="([numero, f]) in formulas" :key="numero">
              <td class="px-4 py-2 border text-center">{{ numero }}</td>

              <td class="px-4 py-2 border">
                <div v-if="f[0]" class="flex items-center gap-2">
                  <span class="semaforo" :class="getSemaforoClase(f[0])"></span>
                  <span>{{ f[0].nombre }} {{ f[0].apellido }}</span>
                </div>
                <span v-else class="text-gray-400">Pendiente</span>

                <br />

                <div v-if="f[1]" class="flex items-center gap-2">
                  <span class="semaforo" :class="getSemaforoClase(f[1])"></span>
                  <span>{{ f[1].nombre }} {{ f[1].apellido }}</span>
                </div>
                <span v-else class="text-gray-400">Pendiente</span>
              </td>

              <td class="px-4 py-2 border">
                <span v-if="f[0]">{{ f[0].puesto }}</span>
                <span v-else class="text-gray-400">Pendiente</span>
                <br />
                <span v-if="f[1]">{{ f[1].puesto }}</span>
                <span v-else class="text-gray-400">Pendiente</span>
              </td>

              <td class="px-4 py-2 border">
                <div v-if="f[0]" class="flex space-x-2">
                  <button v-if="puedeEditarIntegrante" @click="editIntegrante(f[0])" class="px-2 py-1 bg-yellow-700 text-white rounded">
                    Editar
                  </button>
                  <button v-if="puedeEditarIntegrante" @click="solicitarEliminacion(f[0].id)" class="px-2 py-1 bg-red-500 text-white rounded">
                    Eliminar
                  </button>
                  <button @click="$inertia.get(route('docu.index', f[0].id))"
                    class="px-2 py-1 bg-red-800 text-white rounded flex items-center">
                    <FolderOpenIcon class="w-5 h-5 mr-1" /> Documentos
                  </button>
                </div>

                <div v-if="f[1]" class="flex space-x-2 mt-2">
                  <button v-if="puedeEditarIntegrante" @click="editIntegrante(f[1])" class="px-2 py-1 bg-yellow-700 text-white rounded">
                    Editar
                  </button>
                  <button v-if="puedeEditarIntegrante" @click="solicitarEliminacion(f[1].id)" class="px-2 py-1 bg-red-500 text-white rounded">
                    Eliminar
                  </button>
                  <button @click="$inertia.get(route('docu.index', f[1].id))"
                    class="px-2 py-1 bg-red-800 text-white rounded flex items-center">
                    <FolderOpenIcon class="w-5 h-5 mr-1" /> Documentos
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-else class="text-gray-500">No hay integrantes registrados.</p>
    </div>

    <!-- MODAL EDITAR DESCRIPCIÓN -->
    <div v-if="showDescripcionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
      <div class="bg-white rounded-lg p-6 w-full max-w-lg">
        <h2 class="text-lg font-bold mb-4">Editar descripción del consejo</h2>

        <textarea v-model="descripcionForm.descripcion" rows="4"
          class="w-full border rounded px-3 py-2 mb-4"></textarea>

        <div class="flex justify-end space-x-2">
          <button @click="showDescripcionModal = false" class="px-4 py-2 bg-gray-300 rounded">
            Cancelar
          </button>

          <button @click="submitDescripcion" :disabled="descripcionForm.processing"
            class="px-4 py-2 bg-black text-white rounded hover:bg-gray-700 disabled:opacity-50">
            Guardar
          </button>
        </div>
      </div>
    </div>

    <!-- FORMULARIO -->
    <Form :show="showForm" :form="form" :editingId="editingId" @close="showForm = false" @submit="submitForm" />

    <!-- MODAL DE BAJA -->
    <DraggableModal v-if="showDeleteModal" title="Registrar baja de integrante" max-width="max-w-md"
      @close="showDeleteModal =false">
      <!--motivo-->
      <label class="block font-semibold mb-1">
        Motivo
      </label>

      <select v-model="bajaForm.motivo" class="w-full border rounded px-3 py-2 mb-4">
        <option value=""> Seleccione un motivo de baja </option>
        <option value="inasistencia"> Inasistencia </option>
        <option value="sancion"> Sanciones </option>
        <option value="fin_periodo"> No realizó periodo de reeleción / Fin de periodo </option>
        <option value="renuncia"> Renuncia </option>
        <option value="error_registro"> Error de registro </option>
      </select>

      <!--fecha-->
      <div v-if="!esErrorRegistro" class="mb-4">
        <label class="block font-semibold mb-1">
          Fecha de baja
        </label>
        <input type="date" v-model="bajaForm.fecha_baja" class="w-full border rounded px-3 py-2"/>
      </div>

      <!--evidencia PDF-->
      <div v-if="!esErrorRegistro" class="mb-4">
        <label class="block font-semibold mb-1">
          Evidencia PDF
        </label>
        <input type="file" accept="application/pdf" class="w-full border rounden px-3 py-2"
          @change="e=> bajaForm.evidencia_pdf = e.target.files[0]"/>
      </div>

      <!--Botones-->
      <div class="flex justify-end space-x-2 pt-4">
        <button type="button" @click="showDeleteModal = false" class="px-4 py-2 bg-gray-300 rounded">
          Cancelar
        </button>

        <button type="button" @click="confirmarEliminacion"
            :disabled="
                !bajaForm.motivo ||
                (
                    !esErrorRegistro &&
                    (
                        !bajaForm.fecha_baja ||
                        !bajaForm.evidencia_pdf
                    )
                )"
            class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-800 disabled:opacity-50">
            Confirmar baja
        </button>
      </div>
    </DraggableModal>
  </AuthenticatedLayout>
</template>

<style>
.semaforo {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
}
</style>
