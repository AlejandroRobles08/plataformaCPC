<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import PrivacyModal from '@/Components/PrivacyModal.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { computed, nextTick, ref } from 'vue'
import {
  UserIcon,
  EnvelopeIcon,
  UserGroupIcon,
  CheckCircleIcon,
  DocumentTextIcon,
  InformationCircleIcon,
  ArrowRightIcon,
  ArrowLeftIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  consejos: { type: Array, default: () => [] },
  perfiles: { type: Array, default: () => [] },
  generos: { type: Array, default: () => [] },
})

// Documentos que se pedirán en el segundo filtro, para que la persona los vaya preparando
const DOCUMENTOS_SEGUNDO_FILTRO = [
  'Identificación oficial (INE)',
  'Comprobante de domicilio',
  'Carta bajo protesta de decir verdad (art. 170)',
  'Integración de fórmula',
  'Currículum vitae',
  'Carta de motivos',
  'Carta de cumplimiento de normatividad',
]

const DESCRIPCION_MIN = 30
const DESCRIPCION_MAX = 1500

const form = useForm({
  nombre: '',
  apellido_paterno: '',
  apellido_materno: '',
  genero: '',
  correo: '',
  direccion: '',
  consejo_solicitado_id: null,
  perfil: '',
  perfil_descripcion: '',
  aviso_privacidad: false,
  sitio_web: '', // campo trampa para bots
})

const pasos = [
  { titulo: 'Sobre usted', icono: UserIcon, campos: ['nombre', 'apellido_paterno', 'apellido_materno', 'genero'] },
  { titulo: 'Contacto', icono: EnvelopeIcon, campos: ['correo', 'direccion'] },
  { titulo: 'Su participación', icono: UserGroupIcon, campos: ['consejo_solicitado_id', 'perfil', 'perfil_descripcion'] },
  { titulo: 'Revisar y enviar', icono: CheckCircleIcon, campos: ['aviso_privacidad'] },
]

const paso = ref(0)
const formularioRef = ref(null)
const mostrarFormulario = ref(false)
const showPrivacy = ref(false)

const correoValido = v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim())

// Validación rápida en el navegador; la definitiva la hace el servidor
const validarPaso = indice => {
  const errores = {}
  const f = form

  if (indice === 0) {
    if (!f.nombre.trim()) errores.nombre = 'Escriba su nombre o nombres.'
    if (!f.apellido_paterno.trim()) errores.apellido_paterno = 'Escriba su primer apellido.'
    if (!f.genero) errores.genero = 'Elija una opción. Puede seleccionar "Prefiero no responder".'
  }
  if (indice === 1) {
    if (!correoValido(f.correo)) errores.correo = 'Escriba un correo válido, por ejemplo: nombre@correo.com'
    if (!f.direccion.trim()) errores.direccion = 'Escriba su domicilio.'
  }
  if (indice === 2) {
    if (!f.consejo_solicitado_id) errores.consejo_solicitado_id = 'Elija el consejo en el que le gustaría participar.'
    if (!f.perfil) errores.perfil = 'Elija el perfil que mejor le describa.'
    if (f.perfil_descripcion.trim().length < DESCRIPCION_MIN)
      errores.perfil_descripcion = `Por favor, cuéntenos un poco más sobre usted (al menos ${DESCRIPCION_MIN} caracteres).`
  }
  if (indice === 3) {
    if (!f.aviso_privacidad) errores.aviso_privacidad = 'Para enviar su postulación es necesario aceptar el aviso de privacidad.'
  }

  pasos[indice].campos.forEach(c => form.clearErrors(c))
  if (Object.keys(errores).length) {
    form.setError(errores)
    return false
  }
  return true
}

const irAFormulario = async () => {
  mostrarFormulario.value = true
  await nextTick()
  formularioRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const subirAlFormulario = () =>
  nextTick(() => formularioRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' }))

const siguiente = () => {
  if (!validarPaso(paso.value)) return
  paso.value++
  subirAlFormulario()
}

const anterior = () => {
  paso.value--
  subirAlFormulario()
}

// Solo se puede saltar a pasos anteriores, o al siguiente si el actual está completo
const irAPaso = indice => {
  if (indice < paso.value) {
    paso.value = indice
    return
  }
  for (let i = paso.value; i < indice; i++) {
    if (!validarPaso(i)) {
      paso.value = i
      return
    }
  }
  paso.value = indice
}

const enviar = () => {
  if (!validarPaso(3)) return

  form.post(route('postulacion-abierta.store'), {
    preserveScroll: true,
    onError: errores => {
      // Regresa al primer paso que tenga un error del servidor
      const indice = pasos.findIndex(p => p.campos.some(c => errores[c]))
      if (indice !== -1) paso.value = indice
      subirAlFormulario()
    },
  })
}

const aceptarPrivacidad = () => {
  form.aviso_privacidad = true
  form.clearErrors('aviso_privacidad')
  showPrivacy.value = false
}

const rechazarPrivacidad = () => {
  form.aviso_privacidad = false
  showPrivacy.value = false
}

const consejoElegido = computed(() =>
  props.consejos.find(c => c.id === form.consejo_solicitado_id)
)

const nombreCompleto = computed(() =>
  [form.nombre, form.apellido_paterno, form.apellido_materno].map(s => s.trim()).filter(Boolean).join(' ')
)

const resumen = computed(() => [
  { etiqueta: 'Nombre', valor: nombreCompleto.value, paso: 0 },
  { etiqueta: 'Género', valor: form.genero, paso: 0 },
  { etiqueta: 'Correo electrónico', valor: form.correo, paso: 1 },
  { etiqueta: 'Domicilio', valor: form.direccion, paso: 1 },
  { etiqueta: 'Consejo de su interés', valor: consejoElegido.value?.nombre, paso: 2 },
  { etiqueta: 'Perfil', valor: form.perfil, paso: 2 },
  { etiqueta: 'Sobre usted', valor: form.perfil_descripcion, paso: 2 },
])

const inputClass = campo => [
  'mt-1 block w-full rounded-md shadow-sm focus:border-green-700 focus:ring-green-700',
  form.errors[campo] ? 'border-red-400' : 'border-gray-300',
]
</script>

<template>
  <PublicLayout>
    <Head title="Postulación abierta" />

    <!-- Bienvenida -->
    <section class="bg-white rounded-lg shadow-sm overflow-hidden">
      <div class="px-6 py-8 sm:px-10 text-white" style="background-color:#1E520B;">
        <p class="text-sm font-semibold tracking-wide uppercase opacity-80">Convocatoria abierta</p>
        <h1 class="mt-2 text-2xl sm:text-3xl font-bold">Le damos la bienvenida</h1>
        <p class="mt-3 max-w-2xl text-base opacity-90">
          Gracias por su interés en formar parte de los Consejos de Participación Ciudadana.
          Su voz y su experiencia pueden ayudar a construir mejores decisiones para nuestra comunidad.
        </p>
      </div>

      <div class="px-6 py-8 sm:px-10 grid gap-8 md:grid-cols-2">
        <div>
          <h2 class="text-lg font-semibold text-gray-800">¿Cómo funciona?</h2>
          <ol class="mt-4 space-y-4">
            <li class="flex gap-3">
              <span class="flex items-center justify-center w-7 h-7 shrink-0 rounded-full bg-green-100 text-green-800 text-sm font-bold">1</span>
              <p class="text-sm text-gray-600">
                <span class="font-semibold text-gray-800">Llene su postulación.</span>
                Le pediremos algunos datos personales, de contacto, el consejo en el que le gustaría participar y su perfil.
                Toma alrededor de 5 minutos y <span class="font-medium">no necesita adjuntar documentos</span>.
              </p>
            </li>
            <li class="flex gap-3">
              <span class="flex items-center justify-center w-7 h-7 shrink-0 rounded-full bg-green-100 text-green-800 text-sm font-bold">2</span>
              <p class="text-sm text-gray-600">
                <span class="font-semibold text-gray-800">Reciba un correo de confirmación.</span>
                Al enviar su postulación le enviaremos un correo confirmando que la recibimos.
              </p>
            </li>
            <li class="flex gap-3">
              <span class="flex items-center justify-center w-7 h-7 shrink-0 rounded-full bg-green-100 text-green-800 text-sm font-bold">3</span>
              <p class="text-sm text-gray-600">
                <span class="font-semibold text-gray-800">Revisión.</span>
                El equipo de los consejos revisará su postulación y le escribirá al correo que registre.
              </p>
            </li>
            <li class="flex gap-3">
              <span class="flex items-center justify-center w-7 h-7 shrink-0 rounded-full bg-green-100 text-green-800 text-sm font-bold">4</span>
              <p class="text-sm text-gray-600">
                <span class="font-semibold text-gray-800">Entrega de documentos.</span>
                Si su postulación avanza, recibirá un acceso a la plataforma para subir sus documentos.
              </p>
            </li>
          </ol>
        </div>

        <div class="rounded-lg border border-amber-200 bg-amber-50 p-5">
          <div class="flex items-center gap-2 text-amber-800">
            <DocumentTextIcon class="w-5 h-5" aria-hidden="true" />
            <h2 class="font-semibold">Vaya preparando sus documentos</h2>
          </div>
          <p class="mt-2 text-sm text-amber-900">
            Por ahora no los necesita, pero si su postulación avanza se los pediremos en PDF:
          </p>
          <ul class="mt-3 space-y-1 text-sm text-amber-900 list-disc list-inside">
            <li v-for="doc in DOCUMENTOS_SEGUNDO_FILTRO" :key="doc">{{ doc }}</li>
          </ul>
        </div>
      </div>

      <div v-if="!mostrarFormulario" class="px-6 pb-8 sm:px-10">
        <button type="button" @click="irAFormulario"
          class="inline-flex items-center gap-2 px-5 py-3 rounded-md text-white font-semibold hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-700"
          style="background-color:#1E520B;">
          Comenzar mi postulación
          <ArrowRightIcon class="w-5 h-5" aria-hidden="true" />
        </button>
      </div>
    </section>

    <!-- Formulario -->
    <section v-if="mostrarFormulario" ref="formularioRef" class="mt-8 bg-white rounded-lg shadow-sm scroll-mt-4">
      <!-- Indicador de pasos -->
      <nav aria-label="Pasos de la postulación" class="border-b border-gray-200 px-4 py-4 sm:px-10">
        <ol class="flex items-center justify-between gap-2">
          <li v-for="(p, i) in pasos" :key="p.titulo" class="flex-1">
            <button type="button" @click="irAPaso(i)"
              class="w-full flex flex-col sm:flex-row items-center gap-2 text-center sm:text-left"
              :aria-current="i === paso ? 'step' : undefined">
              <span :class="[
                'flex items-center justify-center w-9 h-9 shrink-0 rounded-full border-2 transition',
                i < paso ? 'bg-green-700 border-green-700 text-white'
                  : i === paso ? 'border-green-700 text-green-800 bg-green-50'
                  : 'border-gray-300 text-gray-400',
              ]">
                <CheckCircleIcon v-if="i < paso" class="w-5 h-5" aria-hidden="true" />
                <component v-else :is="p.icono" class="w-5 h-5" aria-hidden="true" />
              </span>
              <span :class="['text-xs sm:text-sm font-medium', i === paso ? 'text-gray-900' : 'text-gray-500']">
                {{ p.titulo }}
              </span>
            </button>
          </li>
        </ol>
      </nav>

      <form @submit.prevent="paso === pasos.length - 1 ? enviar() : siguiente()" class="px-6 py-8 sm:px-10" novalidate>
        <!-- Campo trampa: oculto para personas, visible para bots -->
        <div class="hidden" aria-hidden="true">
          <label for="sitio_web">Sitio web</label>
          <input id="sitio_web" v-model="form.sitio_web" type="text" tabindex="-1" autocomplete="off" />
        </div>

        <!-- Paso 1: Sobre usted -->
        <div v-show="paso === 0" class="space-y-5">
          <div>
            <h2 class="text-lg font-semibold text-gray-800">Sobre usted</h2>
            <p class="text-sm text-gray-500">Escriba su nombre tal como aparece en su identificación oficial.</p>
          </div>

          <div class="grid gap-5 md:grid-cols-3">
            <div>
              <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre(s) <span class="text-red-600">*</span></label>
              <input id="nombre" v-model="form.nombre" type="text" autocomplete="given-name" :class="inputClass('nombre')" />
              <p v-if="form.errors.nombre" class="mt-1 text-sm text-red-600">{{ form.errors.nombre }}</p>
            </div>
            <div>
              <label for="apellido_paterno" class="block text-sm font-medium text-gray-700">Primer apellido <span class="text-red-600">*</span></label>
              <input id="apellido_paterno" v-model="form.apellido_paterno" type="text" autocomplete="family-name" :class="inputClass('apellido_paterno')" />
              <p v-if="form.errors.apellido_paterno" class="mt-1 text-sm text-red-600">{{ form.errors.apellido_paterno }}</p>
            </div>
            <div>
              <label for="apellido_materno" class="block text-sm font-medium text-gray-700">Segundo apellido <span class="text-gray-400 font-normal">(opcional)</span></label>
              <input id="apellido_materno" v-model="form.apellido_materno" type="text" :class="inputClass('apellido_materno')" />
              <p v-if="form.errors.apellido_materno" class="mt-1 text-sm text-red-600">{{ form.errors.apellido_materno }}</p>
            </div>
          </div>

          <fieldset>
            <legend class="block text-sm font-medium text-gray-700">¿Con qué género usted se identifica? <span class="text-red-600">*</span></legend>
            <p class="text-xs text-gray-500">Siéntase en libertad de seleccionar la opción "Prefiero no responder", si es el caso.</p>
            <div class="mt-2 flex flex-wrap gap-2">
              <label v-for="g in generos" :key="g"
                :class="[
                  'cursor-pointer px-4 py-2 rounded-full border text-sm transition',
                  form.genero === g ? 'border-green-700 bg-green-50 text-green-900 font-medium' : 'border-gray-300 text-gray-700 hover:bg-gray-50',
                ]">
                <input v-model="form.genero" type="radio" name="genero" :value="g" class="sr-only" />
                {{ g }}
              </label>
            </div>
            <p v-if="form.errors.genero" class="mt-1 text-sm text-red-600">{{ form.errors.genero }}</p>
          </fieldset>
        </div>

        <!-- Paso 2: Contacto -->
        <div v-show="paso === 1" class="space-y-5">
          <div>
            <h2 class="text-lg font-semibold text-gray-800">¿Cómo le contactamos?</h2>
            <p class="text-sm text-gray-500">Le enviaremos la confirmación y el resultado de su postulación a este correo. Revise que esté bien escrito.</p>
          </div>

          <div>
            <label for="correo" class="block text-sm font-medium text-gray-700">Correo electrónico <span class="text-red-600">*</span></label>
            <input id="correo" v-model="form.correo" type="email" autocomplete="email" placeholder="nombre@correo.com" :class="inputClass('correo')" />
            <p v-if="form.errors.correo" class="mt-1 text-sm text-red-600">{{ form.errors.correo }}</p>
          </div>

          <div>
            <label for="direccion" class="block text-sm font-medium text-gray-700">Domicilio <span class="text-red-600">*</span></label>
            <textarea id="direccion" v-model="form.direccion" rows="3" maxlength="500" autocomplete="street-address"
              placeholder="Calle, número, colonia y código postal" :class="inputClass('direccion')"></textarea>
            <p v-if="form.errors.direccion" class="mt-1 text-sm text-red-600">{{ form.errors.direccion }}</p>
          </div>
        </div>

        <!-- Paso 3: Su participación -->
        <div v-show="paso === 2" class="space-y-6">
          <div>
            <h2 class="text-lg font-semibold text-gray-800">Su participación</h2>
            <p class="text-sm text-gray-500">Indíquenos en qué consejo le gustaría participar y desde qué perfil.</p>
          </div>

          <fieldset>
            <legend class="block text-sm font-medium text-gray-700">¿En qué consejo le gustaría participar? <span class="text-red-600">*</span></legend>
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
              <label v-for="c in consejos" :key="c.id"
                :class="[
                  'relative flex flex-col rounded-lg border p-4 transition',
                  form.consejo_solicitado_id === c.id ? 'cursor-pointer border-green-700 ring-1 ring-green-700 bg-green-50'
                    : 'cursor-pointer border-gray-300 hover:border-gray-400',
                ]">
                <input v-model="form.consejo_solicitado_id" type="radio" name="consejo" :value="c.id" class="sr-only" />
                <span class="flex items-start justify-between gap-2">
                  <span class="font-medium text-gray-800">{{ c.nombre }}</span>
                  <CheckCircleIcon v-if="form.consejo_solicitado_id === c.id" class="w-5 h-5 text-green-700 shrink-0" aria-hidden="true" />
                </span>
                <span v-if="c.descripcion" class="mt-1 text-sm text-gray-500 line-clamp-3">{{ c.descripcion }}</span>
              </label>
            </div>
            <p v-if="!consejos.length" class="mt-2 text-sm text-gray-500">Por el momento no hay consejos disponibles.</p>
            <p v-if="form.errors.consejo_solicitado_id" class="mt-1 text-sm text-red-600">{{ form.errors.consejo_solicitado_id }}</p>
          </fieldset>

          <div>
            <label for="perfil" class="block text-sm font-medium text-gray-700">¿Qué perfil le describe mejor? <span class="text-red-600">*</span></label>
            <select id="perfil" v-model="form.perfil" :class="inputClass('perfil')">
              <option value="" disabled>Elija una opción</option>
              <option v-for="p in perfiles" :key="p" :value="p">{{ p }}</option>
            </select>
            <p v-if="form.errors.perfil" class="mt-1 text-sm text-red-600">{{ form.errors.perfil }}</p>
          </div>

          <div>
            <label for="perfil_descripcion" class="block text-sm font-medium text-gray-700">
              Cuéntenos sobre usted <span class="text-red-600">*</span>
            </label>
            <p class="text-xs text-gray-500">
              Su experiencia, a qué se dedica y por qué le interesa participar en este consejo.
            </p>
            <textarea id="perfil_descripcion" v-model="form.perfil_descripcion" rows="5" :maxlength="DESCRIPCION_MAX"
              :class="inputClass('perfil_descripcion')"></textarea>
            <div class="mt-1 flex justify-between gap-2">
              <p v-if="form.errors.perfil_descripcion" class="text-sm text-red-600">{{ form.errors.perfil_descripcion }}</p>
              <span class="ml-auto text-xs text-gray-400">{{ form.perfil_descripcion.length }} / {{ DESCRIPCION_MAX }}</span>
            </div>
          </div>
        </div>

        <!-- Paso 4: Revisar y enviar -->
        <div v-show="paso === 3" class="space-y-6">
          <div>
            <h2 class="text-lg font-semibold text-gray-800">Revise su información</h2>
            <p class="text-sm text-gray-500">Si algo no está bien, puede regresar a corregirlo.</p>
          </div>

          <dl class="divide-y divide-gray-100 rounded-lg border border-gray-200">
            <div v-for="r in resumen" :key="r.etiqueta" class="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4">
              <dt class="text-sm font-medium text-gray-500">{{ r.etiqueta }}</dt>
              <dd class="sm:col-span-2 flex items-start justify-between gap-3 text-sm text-gray-800">
                <span class="whitespace-pre-line break-words min-w-0">{{ r.valor || '—' }}</span>
                <button type="button" @click="paso = r.paso" class="shrink-0 text-xs font-medium text-green-800 hover:underline">
                  Editar
                </button>
              </dd>
            </div>
          </dl>

          <div class="flex gap-3 rounded-lg bg-blue-50 p-4 text-sm text-blue-900">
            <InformationCircleIcon class="w-5 h-5 shrink-0" aria-hidden="true" />
            <p>Al enviar su postulación recibirá un correo de confirmación. La resolución le llegará al correo <span class="font-semibold break-all">{{ form.correo }}</span>.</p>
          </div>

          <div>
            <label class="flex items-start gap-3">
              <input v-model="form.aviso_privacidad" type="checkbox" class="mt-1 rounded border-gray-300 text-green-700 focus:ring-green-700" />
              <span class="text-sm text-gray-700">
                He leído y acepto el
                <button type="button" @click="showPrivacy = true" class="font-medium text-green-800 underline">aviso de privacidad</button>
                para el tratamiento de mis datos personales.
              </span>
            </label>
            <p v-if="form.errors.aviso_privacidad" class="mt-1 text-sm text-red-600">{{ form.errors.aviso_privacidad }}</p>
          </div>
        </div>

        <!-- Navegación -->
        <div class="mt-8 flex flex-col-reverse gap-3 border-t border-gray-100 pt-6 sm:flex-row sm:justify-between">
          <button v-if="paso > 0" type="button" @click="anterior"
            class="inline-flex justify-center items-center gap-2 px-4 py-2 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">
            <ArrowLeftIcon class="w-4 h-4" aria-hidden="true" />
            Regresar
          </button>
          <span v-else></span>

          <button v-if="paso < pasos.length - 1" type="submit"
            class="inline-flex justify-center items-center gap-2 px-5 py-2 rounded-md text-white font-semibold hover:opacity-90"
            style="background-color:#1E520B;">
            Continuar
            <ArrowRightIcon class="w-4 h-4" aria-hidden="true" />
          </button>
          <button v-else type="submit" :disabled="form.processing"
            class="inline-flex justify-center items-center gap-2 px-5 py-2 rounded-md text-white font-semibold hover:opacity-90 disabled:opacity-50"
            style="background-color:#1E520B;">
            {{ form.processing ? 'Enviando…' : 'Enviar mi postulación' }}
          </button>
        </div>
      </form>
    </section>

    <PrivacyModal v-if="showPrivacy" @aceptar="aceptarPrivacidad" @rechazar="rechazarPrivacidad" />
  </PublicLayout>
</template>
