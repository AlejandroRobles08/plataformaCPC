<script>
import { router, usePage, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Swal from "sweetalert2";
import { watch } from "vue";

export default {
  components: { AuthenticatedLayout, Link },

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

        //alerta de reelección aprobada
        if (flash.success) {
          Swal.fire({
            toast: true,
            position: "top-end",
            icon: "success",
            title: flash.success,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
          });
        }

        //alerta de reelección no aprobada
        if (flash.warning) {
          Swal.fire({
            toast: true,
            position: "top-end",
            icon: "warning",
            title: flash.warning,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
          });
        }

        //alerta de error
        if (flash.error) {
          Swal.fire({
            toast: true,
            position: "top-end",
            icon: "error",
            title: flash.error,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
          });
        }
      },
      { immediate: true }
    );

    return {};
  },

  //estado del modal y del historial desplegado
  data() {
    return {
      showModal: false,
      seleccionado: null,
      historialAbierto: null,
    };
  },

  computed: {
    //agrupa los periodos por integrante
    historialPorIntegrante() {
      const grupos = {};

      this.registros.forEach(r => {
        const id = r.integrante_id;

        if (!grupos[id]) {
          grupos[id] = {
            integrante: r.integrante,
            periodos: [],
          };
        }

        grupos[id].periodos.push(r);
      });

      //ordena los periodos del más reciente al más antiguo
      Object.values(grupos).forEach(g => {
        g.periodos.sort(
          (a, b) => new Date(b.inicio_cargo) - new Date(a.inicio_cargo)
        );
      });

      return grupos;
    }
  },

  //funciones para formatear fechas, abrir modal, cerrar modal, aprobar, rechazar, obtener estatus
  methods: {
    formatearFecha(fecha) {
      if (!fecha) return "N/A";
      //se toma solo la parte de la fecha en hora local para evitar el desfase de un día por UTC
      return new Date(`${String(fecha).substring(0, 10)}T00:00:00`)
        .toLocaleDateString("es-MX"); //formato de fecha en español
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

    //aprueba la reelección del periodo seleccionado
    aprobar() {
      router.post(`/legalidad/${this.seleccionado.id}/aprobar`);
      this.cerrarModal();
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

    estado(p) {
      return p.estatus_reeleccion || "pendiente";
    },

    //texto de la columna periodos según si fue reelegido
    vecesReelegido(periodos) {
      //al aprobarse la reelección el registro queda como "aprobado" y marcado como ya_reelegido
      const reelegido = periodos.some(
        p => p.estatus_reeleccion === "aprobado" || p.ya_reelegido
      );

      return reelegido ? "Primer periodo" : "Periodo inicial";
    },

    //muestra u oculta el historial del integrante
    toggleHistorial(integranteId) {
      this.historialAbierto =
        this.historialAbierto === integranteId ? null : integranteId;
    }
  },
};
</script>


<template>
  <AuthenticatedLayout>
    <!-- Navegación -->
    <div class="mb-4">
      <Link :href="route('legalidad.index', {consejo: consejo.id})"
        class="inline-flex items-center px-3 py-2 bg-gray-200 text-gray-800 text-sm rounded hover:bg-gray-300 transition">
      ← Volver a Panel de Integrantes
      </Link>
    </div>

  <div class="p-6 space-y-6">

      <!-- Encabezado -->
      <h1 class="text-2xl font-bold text-center">
        Validación de Reelecciones
        <br />
        <span class="text-gray-600 text-lg">
          Consejo de {{ consejo.nombre }}
        </span>
      </h1>

      <!-- Información de periodos -->
      <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-900 text-sm rounded p-4 space-y-1">
        <p>
          El <strong>"Periodo inicial"</strong> corresponde a la duración en el cargo de la persona consejera,
          desde su alta hasta la fecha posterior a 3 años.
        </p> <br>
        <p>
          El <strong>"Primer periodo"</strong> corresponde a la reelección y, en su caso, aprobación de la misma
          de la persona consejera. Cada persona tendrá derecho a una sola reelección.
        </p>
      </div>

      <!-- Tabla de integrantes -->
      <div class="overflow-x-auto bg-white shadow rounded p-4">
        <table class="min-w-full border-collapse">
          <thead>
            <tr class="bg-gray-100">
              <th class="px-4 py-3 border">Integrante</th>
              <th class="px-4 py-3 border text-center">Periodos</th>
              <th class="px-4 py-3 border text-center">Acciones</th>
            </tr>
          </thead>

          <tbody>
            <template v-for="(grupo, integranteId) in historialPorIntegrante" :key="integranteId">
              <tr class="bg-gray-50 hover:bg-gray-100">
                <td class="px-4 py-3 border font-semibold">
                  {{ grupo.integrante.nombre }}
                  {{ grupo.integrante.apellido }}
                </td>
                <td class="px-4 py-3 border text-center">
                  {{ vecesReelegido(grupo.periodos) }}
                </td>
                <td class="px-4 py-3 border text-center">
                  <button class="px-3 py-1 rounded text-white bg-red-800" @click="toggleHistorial(integranteId)">
                    {{ historialAbierto === integranteId
                      ? "Ocultar historial"
                      : "Ver historial" }}
                  </button>
                </td>
              </tr>

              <!-- Historial de periodos -->
              <tr v-if="historialAbierto === integranteId">
                <td colspan="3" class="bg-white p-4 border">
                  <table class="min-w-full border-collapse">
                    <thead>
                      <tr class="bg-gray-100">
                        <th class="px-3 py-2 border">Inicio</th>
                        <th class="px-3 py-2 border">Fin</th>
                        <th class="px-3 py-2 border text-center">Estatus</th>
                        <th class="px-3 py-2 border text-center">Acciones</th>
                      </tr>
                    </thead>

                    <tbody>
                      <tr v-for="periodo in grupo.periodos" :key="periodo.id" class="hover:bg-gray-50">
                        <td class="px-3 py-2 border">
                          {{ formatearFecha(periodo.inicio_cargo) }}
                        </td>
                        <td class="px-3 py-2 border">
                          {{ formatearFecha(periodo.fin_cargo) }}
                        </td>
                        <td class="px-3 py-2 border text-center font-semibold">
                          {{ estado(periodo) }}
                        </td>
                        <td class="px-3 py-2 border text-center">
                          <button v-if="estado(periodo) === 'pendiente'"
                            class="px-3 py-1 rounded text-white bg-yellow-700" @click="abrirValidacion(periodo)">
                            Validar
                          </button>

                          <button v-else class="px-3 py-1 rounded text-white bg-gray-700"
                            @click="abrirValidacion(periodo)" style="background-color: #A6A6A8;">
                            Ver
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Modal de validación -->
      <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">
        <div class="bg-white rounded shadow p-6 w-96 space-y-4">
          <h2 class="text-xl font-bold text-center">
            Validar documentos
          </h2>

          <!-- Documentos del periodo -->
          <div v-if="seleccionado">
            <p>
              <strong>Integrante:</strong>
              {{ seleccionado.integrante?.nombre }}
              {{ seleccionado.integrante?.apellido }}
            </p>

            <a v-if="seleccionado.doc_nombramiento" :href="`/storage/${seleccionado.doc_nombramiento}`" target="_blank"
              class="block text-blue-600 underline mt-2">
              Ver Nombramiento
            </a>

            <a v-if="seleccionado.doc_carta_reeleccion" :href="`/storage/${seleccionado.doc_carta_reeleccion}`"
              target="_blank" class="block text-blue-600 underline mt-2">
              Ver Carta de Reelección
            </a>

            <a v-if="seleccionado.doc_otros" :href="`/storage/${seleccionado.doc_otros}`" target="_blank"
              class="block text-blue-600 underline mt-2">
              Ver Otros documentos
            </a>

            <!-- Motivo del rechazo -->
            <div v-if="estado(seleccionado) === 'rechazado' && seleccionado.motivo_rechazo"
              class="mt-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-700">
              <strong class="block">Motivo del rechazo:</strong>
              <p class="mt-1 whitespace-pre-line">{{ seleccionado.motivo_rechazo }}</p>
            </div>
          </div>

          <!-- Acciones del modal -->
          <div class="flex justify-between mt-4">
            <button @click="cerrarModal" class="px-4 py-2 bg-gray-500 text-white rounded">
              Cerrar
            </button>

            <div v-if="seleccionado && estado(seleccionado) === 'pendiente' && seleccionado.fecha_inicio_reeleccion"
              class="space-x-2">
              <button @click="no_aprobar" class="px-4 py-2 bg-red-700 text-white rounded">
                No aprobar
              </button>

              <button @click="aprobar" class="px-4 py-2 bg-green-700 text-white rounded">
                Aprobar
              </button>
            </div>
          </div>
        </div>
      </div>
  </div>
  </AuthenticatedLayout>
</template>
