<script>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Form from "./Form.vue";
import { router, Link } from "@inertiajs/vue3";
import Swal from "sweetalert2";

export default {
  components: {
    AuthenticatedLayout,
    Form, Link
  },

  //datos enviados desde el controlador
  props: {
    consejo: Object,
    integrantes: Array,
    registros: Array,
  },

  //estado del modal y del registro seleccionado
  data() {
    return {
      showForm: false,
      selectedLegalidad: null,
    };
  },

  computed: {
    //verifica si el usuario tiene rol de administrador
    isAdmin() {
        const roles = this.$page.props.auth?.roles ?? [];
        return roles.some(r => {
            const nombre = typeof r === 'string' ? r : r.name;
            return nombre === 'admin' || nombre === 'super_admin';
        });
    },
    //reelecciones con documentos enviados en espera de validación
    solicitudesPendientes() {
      return this.registros.filter(r =>
        r.estatus_reeleccion === 'pendiente' && r.fecha_inicio_reeleccion
      ).length;
    },
  },

  //muestra la alerta al cargar y al actualizar la vista
  mounted() {
    this.mostrarAlerta();
  },

  updated() {
    this.mostrarAlerta();
  },

  methods: {

    //mensajes de alerta con sweetalert
    mostrarAlerta() {
      const flash = this.$page.props.flash;

      if (flash && flash.success) {
        Swal.fire({
          icon: "success",
          title: "Periodo creado con éxito",
          confirmButtonColor: "#7A1F32",
        });

        //limpia el mensaje para no repetir la alerta
        this.$page.props.flash.success = null;
      }
    },

    //abre el formulario de creación
    openCreateForm() {
      this.selectedLegalidad = null;
      this.showForm = true;
    },

    closeForm() {
      this.showForm = false;
      this.selectedLegalidad = null;
    },

    //redirige a la vista de validación de reelecciones
    irAEstatus() {
      router.get(route("legalidad.estatus", { consejo: this.consejo.id }));
    },

    //formatea fechas a DD/MM/YYYY
    formatearFecha(fecha) {
      if (!fecha) return "N/A";
      //toma solo los primeros 10 caracteres: YYYY-MM-DD
      const limpia = fecha.substring(0, 10);
      const [year, month, day] = limpia.split("-");
      return `${day}/${month}/${year}`;
    },

    //calcula el tiempo restante en formato YY:MM:DD
    calcularTiempoRestante(finCargo) {
      if (!finCargo) return "N/A";

      const hoy = new Date();
      const fin = new Date(finCargo);

      if (fin < hoy) return "Vencido";

      let years = fin.getFullYear() - hoy.getFullYear();
      let months = fin.getMonth() - hoy.getMonth();
      let days = fin.getDate() - hoy.getDate();

      //ajusta los días si el resultado es negativo
      if (days < 0) {
        months--;
        const lastMonth = new Date(
          hoy.getFullYear(),
          hoy.getMonth() + 1,
          0
        );
        days += lastMonth.getDate();
      }

      //ajusta los meses si el resultado es negativo
      if (months < 0) {
        years--;
        months += 12;
      }

      //formateo con ceros a la izquierda
      const pad = (n) => String(n).padStart(2, "0");
      return `${pad(years)}:${pad(months)}:${pad(days)}`;
    },

    //indica si al periodo le queda menos de un mes
    periodoMenorAUnMes(finCargo) {
      const tiempo = this.calcularTiempoRestante(finCargo);
      if (tiempo === "Vencido" || tiempo === "N/A") return false;

      const [years, months] = tiempo.split(":").map(Number);
      return years === 0 && months === 0;
    },

    estadoReeleccion(item) {
      return item.estatus_reeleccion || "pendiente";
    },
  },
};
</script>

<template>
  <AuthenticatedLayout>
      <!-- Navegación -->
      <div class="mt-6">
      <Link :href="route('consejos.legalidad', consejo.id)" class="text-gray-600 hover:underline">
        ← Volver a Consejos de Participación Ciudadana
      </Link>
    </div>
    <div class="p-6 space-y-6">

      <!-- Encabezado -->
      <h1 class="text-2xl font-bold text-center">
        Periodo en el cargo
        <br />
        <span class="text-gray-600 text-lg">
          Consejo de {{ consejo.nombre }}
        </span>
      </h1>

      <!-- Botones superiores -->
      <div class="flex justify-between items-center">

        <!-- Solicitudes de reelección -->
        <button v-if="isAdmin" @click="irAEstatus" class="relative px-4 py-2 rounded text-white font-semibold"
          style="background-color:#7F977F;">
          Solicitudes
          <span
            v-if="solicitudesPendientes"
            class="absolute -top-2 -right-2 min-w-5 h-5 px-1 flex items-center justify-center rounded-full bg-red-600 text-xs font-bold">
            {{ solicitudesPendientes }}
          </span>
        </button>

        <!-- Crear periodo -->
        <div v-if="isAdmin" class="ml-auto">
          <button @click="openCreateForm" class="px-4 py-2 rounded text-white font-semibold"
            style="background-color:#C7A447;">
            Dar de alta un nuevo periodo
          </button>
        </div>
      </div>

      <!-- Tabla de periodos -->
      <div class="overflow-x-auto bg-white shadow rounded p-4">
        <table class="min-w-full border-collapse">
          <thead>
            <tr class="bg-gray-100">
              <th class="px-4 py-3 border">Integrante</th>
              <th class="px-4 py-3 border">Inicio</th>
              <th class="px-4 py-3 border">Fin</th>
              <th class="px-4 py-3 border text-center">Periodo hábil</th>
              <th class="px-4 py-3 border text-center">Estatus</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="item in registros" :key="item.id" class="hover:bg-gray-50">

              <td class="px-4 py-3 border">
                {{ item.integrante?.nombre }} {{ item.integrante?.apellido }}
              </td>

              <td class="px-4 py-3 border">
                {{ formatearFecha(item.inicio_cargo) }}
              </td>

              <td class="px-4 py-3 border">
                {{ formatearFecha(item.fin_cargo) }}
              </td>

              <td class="px-4 py-3 border text-center font-mono font-semibold rounded" :class="{
                'text-red-700': calcularTiempoRestante(item.fin_cargo) === 'Vencido',
                'bg-yellow-200 text-yellow-900': periodoMenorAUnMes(item.fin_cargo)
              }">
                {{ calcularTiempoRestante(item.fin_cargo) }}
              </td>

              <td class="px-4 py-3 border text-center font-semibold">
                <span v-if="estadoReeleccion(item) === 'aprobado'"
                  class="px-2 py-1 bg-green-200 text-green-900 rounded">
                  Aprobado
                </span>

                <span v-else-if="estadoReeleccion(item) === 'rechazado'"
                  class="px-2 py-1 bg-red-200 text-red-900 rounded">
                  Rechazado
                </span>

                <span v-else class="px-2 py-1 bg-yellow-200 text-yellow-900 rounded">
                  Pendiente
                </span>
              </td>

            </tr>
          </tbody>
        </table>
      </div>

      <!-- Formulario de periodo -->
      <Form :show="showForm" :consejo="consejo" :integrantes="integrantes" :editData="selectedLegalidad"
        @close="closeForm" />
    </div>
  </AuthenticatedLayout>
</template>
