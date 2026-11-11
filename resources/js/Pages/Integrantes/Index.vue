<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import DraggableModal from '@/Components/DraggableModal.vue'
import SemaforoAsistencias from '@/Components/SemaforoAsistencias.vue'
import { ref, computed, onMounted } from 'vue'
import { useForm, Link, usePage, router } from '@inertiajs/vue3'
import { FolderOpenIcon } from '@heroicons/vue/24/solid'
import { PencilIcon } from '@heroicons/vue/24/outline'
import Form from './Form.vue'
import CrearUsuarioModal from '@/Components/CrearUsuarioModal.vue'
import Swal from 'sweetalert2'

const props = defineProps({
  consejo: Object,
  integrantes: Array,
  integranteActualId: { type: Number, default: null },
  consejoLleno: { type: Boolean, default: false },
  // El integrante aún no captura sus datos ("Darme de alta")
  altaPendiente: { type: Boolean, default: false }
})

// Deben coincidir con Integrante::MAX_FORMULAS e Integrante::INTEGRANTES_POR_FORMULA
const MAX_FORMULAS = 15
const INTEGRANTES_POR_FORMULA = 2

const page = usePage()
const roles = computed(() => page.props.auth.roles ?? [])
const esIntegrante = computed(() => roles.value.includes('integrante'))
// Integrantes: sólo su propio registro; administradores y superadmins: todos
const esMiIntegrante = (integrante) => !esIntegrante.value || integrante.id === props.integranteActualId
const puedeEditarIntegrante = computed(() =>
  roles.value.includes('super_admin') || page.props.auth.permissions?.includes('usuarios.editar'))

// Columna derecha con instrucciones: administración o integrante con el alta pendiente
const hayInstrucciones = computed(() => puedeEditarIntegrante.value || (esIntegrante.value && props.altaPendiente))

const exito = (title, text) => Swal.fire({ icon: 'success', title, text, confirmButtonColor: '#16a34a' })

// Aviso cuando el integrante intenta entrar a otro módulo sin completar su alta
onMounted(() => {
  const aviso = page.props.flash?.warning
  if (aviso) Swal.fire({ icon: 'info', title: 'Completa tu alta', text: aviso, confirmButtonColor: '#16a34a' })
})

const showForm = ref(false)
const editingId = ref(null)
// 'editar': el admin edita un integrante; 'alta': el integrante captura sus propios datos
const modoForm = ref('editar')
const showCrearUsuario = ref(false)

// Editar descripción del consejo
const showDescripcionModal = ref(false)
const descripcionForm = useForm({ descripcion: props.consejo.descripcion ?? '' })

function openDescripcionModal() {
  descripcionForm.descripcion = props.consejo.descripcion ?? ''
  showDescripcionModal.value = true
}

function submitDescripcion() {
  descripcionForm.patch(route('consejos.descripcion.update', props.consejo.id), {
    onSuccess: () => { showDescripcionModal.value = false }
  })
}

// Form para editar integrantes / completar alta
// (la fórmula no se captura ni se edita: el backend la asigna al dar de alta)
const form = useForm({
  nombre: '', apellido_paterno: '', apellido_materno: '', cargo: '', correo: '', genero: '',
  direccion: '', discapacidad: false, discapacidad_tipo: '', consejo_id: props.consejo.id,
})

// El admin crea el usuario del integrante (nombre, correo, consejo, fórmula y cargo)
function usuarioCreado() {
  showCrearUsuario.value = false
  exito('Usuario creado', 'El integrante ya puede ingresar con su correo y la contraseña temporal para completar su alta.')
}

// "Darme de alta": el integrante completa sus datos sobre su propio registro
function abrirAlta() {
  const propio = props.integrantes.find(i => i.id === props.integranteActualId)
  if (propio) editIntegrante(propio, 'alta')
}

function editIntegrante(integrante, modo = 'editar') {
  editingId.value = integrante.id
  modoForm.value = modo
  form.clearErrors()
  for (const campo of ['apellido_paterno', 'apellido_materno', 'cargo', 'genero', 'direccion', 'discapacidad_tipo']) {
    form[campo] = integrante[campo] ?? ''
  }
  form.nombre = integrante.nombre
  form.correo = integrante.correo
  form.discapacidad = integrante.discapacidad === 'si'
  form.consejo_id = integrante.consejo_id
  showForm.value = true
}

function submitForm() {
  // Convierte el check a 'si'/'no' solo en los datos enviados, sin tocar el estado del form
  // (mutarlo a 'no', que es truthy, hacía aparecer el campo de tipo de discapacidad)
  form.transform(data => ({ ...data, discapacidad: data.discapacidad ? 'si' : 'no' }))
  const alta = modoForm.value === 'alta'
  form[alta ? 'patch' : 'put'](route(alta ? 'integrantes.alta' : 'integrantes.update', editingId.value), {
    onSuccess: () => {
      form.reset()
      showForm.value = false
      alta
        ? exito('Alta completada', 'Tus datos se guardaron correctamente. Ahora puedes digitalizar tus documentos.')
        : exito('Integrante actualizado', 'Los datos del integrante se actualizaron correctamente.')
    }
  })
}

// Lugares ocupados y cargos asignados en cada fórmula de este consejo
// (el admin recibe todos los integrantes del consejo; se usa al crear y al mover de fórmula)
const ocupacion = computed(() => {
  const totales = {}
  props.integrantes.forEach(i => { if (i.formula) totales[i.formula] = (totales[i.formula] ?? 0) + 1 })
  return totales
})

const cargosPorFormula = computed(() => {
  const cargos = {}
  props.integrantes.forEach(i => { if (i.formula && i.cargo) (cargos[i.formula] ??= []).push(i.cargo) })
  return cargos
})

// MOVER DE FÓRMULA: sólo se ofrecen fórmulas vacías (0/2) o con un lugar libre (1/2)
async function moverFormula() {
  const integrante = props.integrantes.find(i => i.id === editingId.value)
  if (!integrante) return

  const opciones = {}
  for (let n = 1; n <= MAX_FORMULAS; n++) {
    const total = ocupacion.value[n] ?? 0
    if (n !== integrante.formula && total < INTEGRANTES_POR_FORMULA) {
      opciones[n] = `Fórmula ${n} (${total}/${INTEGRANTES_POR_FORMULA})`
    }
  }

  const { value: formula } = await Swal.fire({
    title: 'Mover de fórmula',
    text: `${integrante.nombre_completo} está en la fórmula ${integrante.formula ?? '-'}.`,
    input: 'select',
    inputOptions: opciones,
    inputPlaceholder: 'Seleccione la fórmula paramover al integrante',
    showCancelButton: true,
    confirmButtonText: 'Mover',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#16a34a',
    inputValidator: value => !value && 'Selecciona una fórmula',
  })
  if (!formula) return

  router.patch(route('integrantes.mover', integrante.id), { formula }, {
    preserveScroll: true,
    onSuccess: () => {
      showForm.value = false
      form.reset()
      exito('Integrante movido', `El integrante se movió a la fórmula ${formula}.`)
    },
    onError: errors => Swal.fire({
      icon: 'error',
      title: 'Error al mover de fórmula',
      text: errors.formula ?? errors.cargo ?? 'La fórmula seleccionada se encuentra llena.',
      confirmButtonColor: '#dc2626'
    })
  })
}

// BAJA / ELIMINACIÓN
const showDeleteModal = ref(false)
const integranteAEliminar = ref(null)
const bajaForm = useForm({ motivo: '', fecha_baja: '', evidencia_pdf: null })
const esErrorRegistro = computed(() => bajaForm.motivo === 'error_registro')
const bajaIncompleta = computed(() =>
  !bajaForm.motivo || (!esErrorRegistro.value && (!bajaForm.fecha_baja || !bajaForm.evidencia_pdf)))

function solicitarEliminacion(id) {
  integranteAEliminar.value = id
  bajaForm.reset()
  showDeleteModal.value = true
}

function confirmarEliminacion() {
  if (bajaIncompleta.value) return
  const error = esErrorRegistro.value
  bajaForm.post(route('integrantes.baja', integranteAEliminar.value), {
    forceFormData: !error,
    onSuccess: () => {
      showDeleteModal.value = false
      integranteAEliminar.value = null
      error
        ? exito('Integrante eliminado', 'El integrante se eliminó por error de registro.')
        : exito('Baja registrada', 'La baja del integrante se registró correctamente.')
    }
  })
}

// Agrupar integrantes por fórmula
const formulas = computed(() => {
  const grouped = {}
  props.integrantes.forEach(i => (grouped[i.formula ?? 0] ??= []).push(i))
  return Object.entries(grouped).sort((a, b) => Number(a[0]) - Number(b[0]))
})

// Semaforización de documentos: 0 aprobados rojo, 1-6 amarillo, 7 verde
// (un integrante no ve el semáforo de los demás)
const getSemaforoClase = (integrante) => {
  if (esIntegrante.value && !esMiIntegrante(integrante)) return null
  const aprobados = integrante?.documentos?.filter(d => d.estatus === 'aprobado').length ?? 0
  return aprobados === 0 ? 'bg-red-500' : aprobados < 7 ? 'bg-yellow-400' : 'bg-green-500'
}
</script>

<template>
  <AuthenticatedLayout>
    <Link :href="route('consejos.index', consejo.id)" class="inline-block mt-2 mb-4 text-gray-600 hover:underline">← Volver a Consejos de Participación Ciudadana</Link>
    <div class="p-6">
      <h1 class="mb-4 text-2xl font-bold">Integrantes del Consejo de Participación Ciudadana de {{ consejo.nombre }}</h1>

      <!-- DESCRIPCIÓN CON BOTÓN MINIMALISTA -->
      <div class="flex items-start gap-2 mb-6">
        <p class="text-gray-700">{{ consejo.descripcion }}</p>
        <button v-if="puedeEditarIntegrante" @click="openDescripcionModal"
          class="text-gray-400 hover:text-gray-600 transition" title="Editar descripción">
          <PencilIcon class="w-4 h-4" />
        </button>
      </div>

      <!-- Semaforización (izquierda) e instrucciones según el rol (derecha) -->
      <div class="mb-6 grid grid-cols-1 gap-4" :class="{ 'md:grid-cols-2': hayInstrucciones }">
        <SemaforoAsistencias :es-integrante="esIntegrante" />

        <!-- Instrucciones para administrador -->
        <div v-if="puedeEditarIntegrante" class="space-y-2 rounded-lg border border-gray-200 p-4 text-sm text-gray-600">
          <p>Para dar de alta en el sistema a una persona que ya forma parte de este consejo, de clic en <strong>+ Dar de alta a un integrante</strong> y capture los datos solicitados.</p>
          <p><strong>IMPORTANTE:</strong> El integrante no tendrá acceso a los demás módulos hasta que complete su alta. Si usted capturó algún dato por error, puede corregirlo con el botón <strong>Editar</strong>.</p>
        </div>

        <!-- Instrucciones para integrante con el alta pendiente -->
        <div v-else-if="esIntegrante && altaPendiente" class="space-y-2 rounded-lg border border-gray-200 p-4 text-sm text-gray-600">
          <p>¡Bienvenida(o)! Para comenzar a usar la plataforma, dé clic en el botón <strong>Darme de alta</strong> y complete sus datos. Revise que su nombre esté escrito <strong>correctamente</strong> antes de guardar.</p>
          <p>Su cargo y su correo ya fueron registrados por la administración y no se pueden modificar desde aquí; si alguno es incorrecto, comuníquese con la persona administradora.</p>
          <p><strong>IMPORTANTE:</strong> No tendrá acceso a los demás módulos de la plataforma hasta que complete su alta. Este paso se realiza una sola vez.</p>
        </div>
      </div>

      <p v-if="consejoLleno" class="mb-4 p-3 rounded bg-yellow-50 border border-yellow-300 text-yellow-800">Este consejo ha llegado a su máximo de fórmulas</p>

      <!-- Alta de integrantes -->
      <div v-if="hayInstrucciones" class="mb-4 flex justify-end">
        <button v-if="puedeEditarIntegrante" @click="showCrearUsuario = true" :disabled="consejoLleno"
          class="px-4 py-2 bg-yellow-700 text-white rounded hover:bg-yellow-800 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-black">+ Dar de alta a un integrante</button>
        <!-- Desaparece en cuanto el integrante guarda sus datos -->
        <button v-else-if="esIntegrante && altaPendiente" @click="abrirAlta" class="px-4 py-2 bg-green-700 text-white rounded hover:bg-green-800">Darme de alta</button>
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
                <template v-for="idx in [0, 1]" :key="idx">
                  <br v-if="idx" />
                  <p v-if="f[idx]" class="flex items-center gap-2">
                    <span v-if="getSemaforoClase(f[idx])" class="semaforo" :class="getSemaforoClase(f[idx])"></span>{{ f[idx].nombre_completo }}
                  </p>
                  <span v-else class="text-gray-400">En espera de asignación de Persona Consejera</span>
                </template>
              </td>

              <td class="px-4 py-2 border">
                <template v-for="idx in [0, 1]" :key="idx">
                  <br v-if="idx" />
                  <span v-if="f[idx]">{{ f[idx].cargo }}</span>
                  <span v-else class="text-gray-400">En espera de asignación de cargo</span>
                </template>
              </td>

              <td class="px-4 py-2 border">
                <template v-for="idx in [0, 1]" :key="idx">
                  <div v-if="f[idx]" class="flex space-x-2" :class="{ 'mt-2': idx }">
                    <template v-if="puedeEditarIntegrante">
                      <button @click="editIntegrante(f[idx])" class="px-2 py-1 bg-yellow-700 text-white rounded">Editar</button>
                      <button @click="solicitarEliminacion(f[idx].id)" class="px-2 py-1 bg-red-500 text-white rounded">Eliminar</button>
                    </template>
                    <button v-if="esMiIntegrante(f[idx]) && !(esIntegrante && altaPendiente)" @click="$inertia.get(route('docu.index', f[idx].id))"
                      class="px-2 py-1 bg-red-800 text-white rounded flex items-center"><FolderOpenIcon class="w-5 h-5 mr-1" /> Documentos</button>
                  </div>
                </template>
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
        <textarea v-model="descripcionForm.descripcion" rows="4" class="w-full border rounded px-3 py-2 mb-4"></textarea>
        <div class="flex justify-end space-x-2">
          <button @click="showDescripcionModal = false" class="px-4 py-2 bg-gray-300 rounded">Cancelar</button>
          <button @click="submitDescripcion" :disabled="descripcionForm.processing" class="px-4 py-2 bg-black text-white rounded hover:bg-gray-700 disabled:opacity-50">Guardar</button>
        </div>
      </div>
    </div>

    <!-- FORMULARIO -->
    <Form :show="showForm" :form="form" :editingId="editingId" :modo="modoForm"
      @close="showForm = false" @submit="submitForm" @mover="moverFormula" />

    <!-- CREAR USUARIO INTEGRANTE (admin) -->
    <CrearUsuarioModal v-if="showCrearUsuario" rol="integrante" ruta="integrantes.store"
      titulo="Nuevo usuario integrante" :consejo="consejo" :ocupacion="ocupacion"
      :cargos-por-formula="cargosPorFormula"
      @close="showCrearUsuario = false" @created="usuarioCreado" />

    <!-- MODAL DE BAJA -->
    <DraggableModal v-if="showDeleteModal" title="Registrar baja de integrante" max-width="max-w-md"
      @close="showDeleteModal = false">
      <label class="block font-semibold mb-1">Motivo</label>
      <select v-model="bajaForm.motivo" class="w-full border rounded px-3 py-2 mb-4">
        <option value="">Seleccione un motivo de baja</option>
        <option value="inasistencia">Inasistencia</option>
        <option value="sancion">Sanciones</option>
        <option value="fin_periodo">No realizó periodo de reeleción / Fin de periodo</option>
        <option value="renuncia">Renuncia</option>
        <option value="error_registro">Error de registro</option>
      </select>

      <template v-if="!esErrorRegistro">
        <label class="block font-semibold mb-1">Fecha de baja</label>
        <input type="date" v-model="bajaForm.fecha_baja" class="w-full border rounded px-3 py-2 mb-4" />
        <label class="block font-semibold mb-1">Evidencia PDF</label>
        <input type="file" accept="application/pdf" class="w-full border rounded px-3 py-2 mb-4" @change="e => bajaForm.evidencia_pdf = e.target.files[0]" />
      </template>

      <div class="flex justify-end space-x-2 pt-4">
        <button type="button" @click="showDeleteModal = false" class="px-4 py-2 bg-gray-300 rounded">Cancelar</button>
        <button type="button" @click="confirmarEliminacion" :disabled="bajaIncompleta" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-800 disabled:opacity-50">Confirmar baja</button>
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
