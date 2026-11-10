<script>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Form from "./Form.vue";
import { router, Link } from "@inertiajs/vue3";
import Swal from "sweetalert2";
import { InboxArrowDownIcon, PlusIcon, MagnifyingGlassIcon } from "@heroicons/vue/24/solid";

export default {
  components: { AuthenticatedLayout, Form, Link, InboxArrowDownIcon, PlusIcon, MagnifyingGlassIcon },

  //datos enviados desde el controlador
  props: {
    consejo: Object,
    integrantes: Array,
    registros: Array,
    //ids sin todos sus documentos del Archivo digital aprobados: no se les puede crear periodo
    integrantesSinExpediente: { type: Array, default: () => [] },
  },

  //estado del modal y texto de búsqueda
  data() {
    return {
      showForm: false,
      selectedLegalidad: null,
      busqueda: "",
    };
  },

  computed: {
    //verifica si el usuario tiene rol de administrador
    isAdmin() {
      const roles = this.$page.props.auth?.roles ?? [];
      return roles.some(r => {
        const nombre = typeof r === "string" ? r : r.name;
        return nombre === "admin" || nombre === "super_admin";
      });
    },
    //reelecciones con documentos enviados en espera de validación
    solicitudesPendientes() {
      return this.registros.filter(r => r.estatus_reeleccion === "pendiente" && r.fecha_inicio_reeleccion).length;
    },
    //integrantes sin ningún periodo (vigente o vencido), los únicos a los que se les puede crear uno
    integrantesDisponibles() {
      const conPeriodo = new Set(this.registros.map(r => r.integrante_id));
      return this.integrantes.filter(i => !conPeriodo.has(i.id));
    },
    //registros que coinciden con la búsqueda por nombre
    registrosFiltrados() {
      const texto = this.busqueda.trim().toLowerCase();
      return this.registros.filter(r => !texto || this.nombreCompleto(r).toLowerCase().includes(texto));
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

    nombreCompleto(item) {
      return `${item.integrante?.nombre ?? ""} ${item.integrante?.apellido ?? ""}`.trim();
    },

    //formatea fechas a DD/MM/YYYY
    formatearFecha(fecha) {
      if (!fecha) return "N/A";
      const [year, month, day] = fecha.substring(0, 10).split("-");
      return `${day}/${month}/${year}`;
    },

    //calcula el tiempo restante en años, meses y días; null si ya venció
    tiempoRestante(finCargo) {
      if (!finCargo) return null;
      const [y, m, d] = finCargo.substring(0, 10).split("-").map(Number);
      const fin = new Date(y, m - 1, d);
      const h = new Date();
      const hoy = new Date(h.getFullYear(), h.getMonth(), h.getDate());
      if (fin < hoy) return null;

      let years = fin.getFullYear() - hoy.getFullYear();
      let months = fin.getMonth() - hoy.getMonth();
      let days = fin.getDate() - hoy.getDate();

      //ajusta los días si el resultado es negativo
      if (days < 0) {
        months--;
        days += new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0).getDate();
      }
      //ajusta los meses si el resultado es negativo
      if (months < 0) {
        years--;
        months += 12;
      }
      return { years, months, days };
    },

    //texto legible del tiempo restante, p. ej. "2 años, 3 meses, 5 días"
    textoRestante(finCargo) {
      const t = this.tiempoRestante(finCargo);
      if (!t) return finCargo ? "Vencido" : "N/A";
      const partes = [];
      if (t.years) partes.push(`${t.years} ${t.years === 1 ? "año" : "años"}`);
      if (t.months) partes.push(`${t.months} ${t.months === 1 ? "mes" : "meses"}`);
      if (t.days || !partes.length) partes.push(`${t.days} ${t.days === 1 ? "día" : "días"}`);
      return partes.join(", ");
    },

    //indica si al periodo le queda menos de un mes
    periodoMenorAUnMes(finCargo) {
      const t = this.tiempoRestante(finCargo);
      return !!t && t.years === 0 && t.months === 0;
    },

    etiquetaReeleccion(item) {
      if (item.estatus_reeleccion === "aprobado") {
        return { texto: "Reelección aprobada", clase: "bg-green-100 text-green-800" };
      }
      if (item.estatus_reeleccion === "rechazado") {
        return { texto: "Reelección rechazada", clase: "bg-red-100 text-red-800" };
      }
      if (item.fecha_inicio_reeleccion) {
        return { texto: "Solicitud en revisión", clase: "bg-yellow-100 text-yellow-800" };
      }
      return { texto: "Sin solicitud", clase: "bg-gray-100 text-gray-600" };
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

      <!-- Encabezado y acciones -->
      <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
          <p class="text-sm font-semibold uppercase tracking-wide" style="color:#7A1F32;">
            Periodo en el cargo
          </p>
          <h1 class="text-2xl font-bold text-gray-900">
            Consejo de {{ consejo.nombre }}
          </h1>
          <p class="text-sm text-gray-500 mt-1">
            Consulta la vigencia de cada integrante y da seguimiento a las solicitudes de reelección.
          </p>
        </div>

        <div v-if="isAdmin" class="flex flex-wrap gap-2">
          <button @click="irAEstatus"
            class="relative inline-flex items-center gap-2 px-4 py-2 rounded-lg border font-semibold bg-white hover:bg-gray-50 transition"
            style="border-color:#7F977F; color:#4d634d;">
            <InboxArrowDownIcon class="w-5 h-5" />
            Solicitudes de reelección
            <span v-if="solicitudesPendientes"
              class="absolute -top-2 -right-2 min-w-5 h-5 px-1 flex items-center justify-center rounded-full bg-red-600 text-white text-xs font-bold">
              {{ solicitudesPendientes }}
            </span>
          </button>

          <button @click="openCreateForm"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-white font-semibold shadow-sm hover:opacity-90 transition"
            style="background-color:#7A1F32;">
            <PlusIcon class="w-5 h-5" />
            Nuevo periodo
          </button>
        </div>
      </div>

      <!-- Búsqueda -->
      <div class="relative md:w-72 md:ml-auto">
        <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
        <input v-model="busqueda" type="text" placeholder="Buscar integrante..."
          class="w-full pl-10 pr-3 py-2 rounded-lg border-gray-300 text-sm focus:border-[#7A1F32] focus:ring-[#7A1F32]" />
      </div>

      <!-- Tabla de periodos -->
      <div class="bg-white rounded-xl shadow-sm border overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Integrante</th>
              <th class="px-4 py-3 text-left">Periodo</th>
              <th class="px-4 py-3 text-left">Tiempo restante</th>
              <th class="px-4 py-3 text-center">Reelección</th>
            </tr>
          </thead>

          <tbody class="divide-y">
            <tr v-for="item in registrosFiltrados" :key="item.id" class="hover:bg-gray-50 transition"
              :class="{ 'bg-yellow-50': periodoMenorAUnMes(item.fin_cargo) }">
              <td class="px-4 py-3 font-semibold text-gray-900">{{ nombreCompleto(item) }}</td>

              <td class="px-4 py-3 whitespace-nowrap text-gray-700">
                {{ formatearFecha(item.inicio_cargo) }}
                <span class="text-gray-400 mx-1">→</span>
                {{ formatearFecha(item.fin_cargo) }}
              </td>

              <td class="px-4 py-3 font-medium"
                :class="textoRestante(item.fin_cargo) === 'Vencido' ? 'text-red-700' : 'text-gray-800'">
                {{ textoRestante(item.fin_cargo) }}
              </td>

              <td class="px-4 py-3 text-center">
                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold whitespace-nowrap"
                  :class="etiquetaReeleccion(item).clase">
                  {{ etiquetaReeleccion(item).texto }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>

        <p v-if="!registrosFiltrados.length" class="p-6 text-center text-gray-500">
          No hay periodos para mostrar.
        </p>
      </div>

      <!-- Formulario de periodo -->
      <Form :show="showForm" :consejo="consejo" :integrantes="integrantesDisponibles"
        :integrantes-sin-expediente="integrantesSinExpediente" :editData="selectedLegalidad"
        @close="closeForm" />
    </div>
  </AuthenticatedLayout>
</template>
