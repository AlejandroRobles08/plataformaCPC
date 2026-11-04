<script>
import { router, usePage, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Swal from "sweetalert2";
import { watch } from "vue";
import { DocumentTextIcon, MagnifyingGlassIcon } from "@heroicons/vue/24/solid";

export default {
  components: { AuthenticatedLayout, Link, DocumentTextIcon, MagnifyingGlassIcon },

  //datos enviados desde el controlador
  props: {
    consejo: Object,
    registros: Array,
  },

  setup() {
    const page = usePage();

    //mensaje de confirmacion despues de aprobar o rechazar una reeleccion
    watch(
      () => page.props.flash,
      (flash) => {
        if (!flash) return;
        ["success", "warning", "error"].forEach(tipo => {
          if (!flash[tipo]) return;
          Swal.fire({
            toast: true,
            position: "top-end",
            icon: tipo,
            title: flash[tipo],
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
          });
        });
      },
      { immediate: true }
    );

    return {};
  },

  //estado del modal y texto de búsqueda
  data() {
    return {
      showModal: false,
      seleccionado: null,
      busqueda: "",
    };
  },

  computed: {
    //registros que coinciden con la búsqueda por nombre
    registrosFiltrados() {
      const texto = this.busqueda.trim().toLowerCase();
      return this.registros.filter(r => !texto || this.nombreCompleto(r).toLowerCase().includes(texto));
    },
  },

  methods: {
    formatearFecha(fecha) {
      if (!fecha) return "—";
      //se toma solo la parte de la fecha en hora local para evitar el desfase de un día por UTC
      return new Date(`${String(fecha).substring(0, 10)}T00:00:00`).toLocaleDateString("es-MX");
    },

    nombreCompleto(p) {
      return `${p.integrante?.nombre ?? ""} ${p.integrante?.apellido ?? ""}`.trim();
    },

    //en_revision (documentos enviados), aprobado, rechazado o sin_solicitud
    estado(p) {
      if (p.estatus_reeleccion === "aprobado") return "aprobado";
      if (p.estatus_reeleccion === "rechazado") return "rechazado";
      return p.fecha_inicio_reeleccion ? "en_revision" : "sin_solicitud";
    },

    etiqueta(p) {
      return {
        en_revision: { texto: "Por validar", clase: "bg-yellow-100 text-yellow-800" },
        aprobado: { texto: "Aprobada", clase: "bg-green-100 text-green-800" },
        rechazado: { texto: "No aprobada", clase: "bg-red-100 text-red-800" },
        sin_solicitud: { texto: "Sin solicitud", clase: "bg-gray-100 text-gray-600" },
      }[this.estado(p)];
    },

    //"Primer periodo" cuando ya se aprobó la reelección, "Periodo inicial" en otro caso
    tipoPeriodo(p) {
      return p.ya_reelegido || p.estatus_reeleccion === "aprobado" ? "Primer periodo" : "Periodo inicial";
    },

    documentos(p) {
      return [
        { campo: "doc_nombramiento", nombre: "Nombramiento" },
        { campo: "doc_carta_reeleccion", nombre: "Carta de reelección" },
        { campo: "doc_otros", nombre: "Otros documentos" },
      ].filter(d => p[d.campo]);
    },

    //abre el modal con el periodo seleccionado
    abrirValidacion(periodo) {
      this.seleccionado = periodo;
      this.showModal = true;
    },

    cerrarModal() {
      this.showModal = false;
      this.seleccionado = null;
    },

    //confirma y aprueba la reelección; reinicia el periodo por 3 años desde hoy
    aprobar() {
      Swal.fire({
        title: "¿Aprobar la reelección?",
        text: "El periodo se reiniciará por 3 años a partir de hoy. Esta acción no se puede deshacer.",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Aprobar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#15803d",
      }).then(result => {
        if (!result.isConfirmed) return;
        router.post(`/legalidad/${this.seleccionado.id}/aprobar`, {}, {
          preserveScroll: true,
          onSuccess: () => this.cerrarModal(),
        });
      });
    },

    //solicita el motivo y rechaza la reelección
    no_aprobar() {
      Swal.fire({
        title: "No aprobar la reelección",
        text: "La solicitud quedará rechazada de forma definitiva.",
        icon: "warning",
        input: "textarea",
        inputPlaceholder: "Describa los motivos por los que no se aprobará la reelección.",
        inputAttributes: { maxlength: 1000 },
        inputValidator: value => !value?.trim() && "Indica el motivo del rechazo.",
        showCancelButton: true,
        confirmButtonText: "No aprobar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#b91c1c",
      }).then(result => {
        if (!result.isConfirmed) return;

        //envía el motivo del rechazo al servidor
        router.post(
          `/legalidad/${this.seleccionado.id}/rechazar`,
          { motivo_rechazo: result.value.trim() },
          {
            preserveScroll: true,
            onSuccess: () => this.cerrarModal(),
            onError: errors => {
              Swal.fire({
                icon: "error",
                title: "Error",
                text: errors.motivo_rechazo ?? "No fue posible rechazar la solicitud.",
              });
            },
          }
        );
      });
    },
  },
};
</script>


<template>
  <AuthenticatedLayout>
    <!-- Navegación -->
    <div class="mt-6">
      <Link :href="route('legalidad.index', { consejo: consejo.id })" class="text-gray-600 hover:underline">
        ← Volver a Periodo en el cargo
      </Link>
    </div>

    <div class="p-6 space-y-6">

      <!-- Encabezado -->
      <div>
        <p class="text-sm font-semibold uppercase tracking-wide" style="color:#7A1F32;">
          Validación de reelecciones
        </p>
        <h1 class="text-2xl font-bold text-gray-900">Consejo de {{ consejo.nombre }}</h1>
        <p class="text-sm text-gray-500 mt-1">
          Revisa los documentos enviados por las personas consejeras y aprueba o rechaza su reelección.
        </p>
      </div>

      <!-- Información de periodos -->
      <div class="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-900 text-sm rounded p-4 space-y-2">
        <p>
          El <strong>"Periodo inicial"</strong> corresponde a la duración en el cargo de la persona consejera,
          desde su alta hasta la fecha posterior a 3 años.
        </p>
        <p>
          El <strong>"Primer periodo"</strong> corresponde a la reelección y la posible aprobación del periodo
          de la persona consejera. Cada persona tendrá derecho a una sola reelección.
        </p>
      </div>

      <!-- Búsqueda -->
      <div class="relative md:w-72 md:ml-auto">
        <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
        <input v-model="busqueda" type="text" placeholder="Buscar integrante..."
          class="w-full pl-10 pr-3 py-2 rounded-lg border-gray-300 text-sm focus:border-[#7A1F32] focus:ring-[#7A1F32]" />
      </div>

      <!-- Tabla de solicitudes -->
      <div class="bg-white rounded-xl shadow-sm border overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Integrante</th>
              <th class="px-4 py-3 text-left">Periodo</th>
              <th class="px-4 py-3 text-left">Tipo</th>
              <th class="px-4 py-3 text-center">Estatus</th>
              <th class="px-4 py-3 text-center">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="p in registrosFiltrados" :key="p.id" class="hover:bg-gray-50 transition">
              <td class="px-4 py-3 font-semibold text-gray-900">{{ nombreCompleto(p) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-gray-700">
                {{ formatearFecha(p.inicio_cargo) }} <span class="text-gray-400 mx-1">→</span> {{ formatearFecha(p.fin_cargo) }}
              </td>
              <td class="px-4 py-3 text-gray-700">{{ tipoPeriodo(p) }}</td>
              <td class="px-4 py-3 text-center">
                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold whitespace-nowrap"
                  :class="etiqueta(p).clase">
                  {{ etiqueta(p).texto }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <button v-if="estado(p) !== 'sin_solicitud'" @click="abrirValidacion(p)"
                  class="px-3 py-1.5 rounded-lg text-xs font-semibold"
                  :class="estado(p) === 'en_revision' ? 'text-white' : 'border text-gray-700 hover:bg-gray-50'"
                  :style="estado(p) === 'en_revision' ? 'background-color:#C7A447;' : ''">
                  {{ estado(p) === 'en_revision' ? 'Validar' : 'Ver' }}
                </button>
                <span v-else class="text-xs text-gray-400">—</span>
              </td>
            </tr>
          </tbody>
        </table>

        <p v-if="!registrosFiltrados.length" class="p-6 text-center text-gray-500">
          No hay registros para mostrar.
        </p>
      </div>

      <!-- Modal de detalle / validación -->
      <div v-if="showModal && seleccionado" class="fixed inset-0 bg-black/50 flex justify-center items-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4 text-sm">
          <div>
            <h2 class="text-lg font-bold">Validar reelección</h2>
            <p class="text-gray-500">{{ nombreCompleto(seleccionado) }} · {{ tipoPeriodo(seleccionado) }}</p>
          </div>

          <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold" :class="etiqueta(seleccionado).clase">
            {{ etiqueta(seleccionado).texto }}
          </span>

          <!-- Documentos del periodo -->
          <div class="space-y-2">
            <a v-for="d in documentos(seleccionado)" :key="d.campo" :href="`/storage/${seleccionado[d.campo]}`"
              target="_blank" class="flex items-center gap-2 px-3 py-2 rounded-lg border font-medium hover:bg-gray-50">
              <DocumentTextIcon class="w-5 h-5" style="color:#7A1F32;" />
              {{ d.nombre }}
            </a>
          </div>

          <!-- Motivo del rechazo -->
          <div v-if="estado(seleccionado) === 'rechazado' && seleccionado.motivo_rechazo"
            class="rounded-lg border border-red-300 bg-red-50 p-3 text-red-700">
            <strong class="block">Motivo del rechazo:</strong>
            <p class="mt-1 whitespace-pre-line">{{ seleccionado.motivo_rechazo }}</p>
          </div>

          <!-- Acciones del modal -->
          <div class="flex justify-end gap-2 pt-2">
            <button @click="cerrarModal" class="px-4 py-2 rounded-lg border text-gray-700 font-medium hover:bg-gray-50">
              Cerrar
            </button>
            <template v-if="estado(seleccionado) === 'en_revision'">
              <button @click="no_aprobar" class="px-4 py-2 rounded-lg border border-red-300 text-red-700 font-semibold hover:bg-red-50">
                No aprobar
              </button>
              <button @click="aprobar" class="px-4 py-2 rounded-lg bg-green-700 text-white font-semibold hover:bg-green-800">
                Aprobar
              </button>
            </template>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
