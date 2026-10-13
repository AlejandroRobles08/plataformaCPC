
<script>
import { router } from "@inertiajs/vue3";
import DraggableModal from "@/Components/DraggableModal.vue";

export default {
  components: {
    DraggableModal,
  },

  //datos recibidos desde la vista Index
  props: {
    show: Boolean,
    consejo: Object,
    integrantes: {
      type: Array,
      default: () => [],
    },
    editData: {
      type: Object,
      default: null,
    },
  },

  //campos del formulario de periodo
  data() {
    return {
      form: {
        integrante_id: "",
        inicio_cargo: "",
        fin_cargo: "",
        periodo_habil: "",
        doc_nombramiento: null,
        doc_carta_reeleccion: null,
        doc_otros: null,
      },
    };
  },

  computed: {
    //si existe un registro seleccionado el formulario es de reelección
    esReeleccion() {
      return !!this.editData;
    },
  },

  watch: {
    //se ejecuta cuando cambia el registro seleccionado
    editData: {
      immediate: true,

      handler(nuevoRegistro) {
        if (nuevoRegistro) {
          this.cargarDatos(nuevoRegistro);
        } else {
          this.limpiarFormulario();
        }
      },
    },
  },

  methods: {
    //carga los datos del registro en el formulario
    cargarDatos(registro) {
      this.form.integrante_id = registro.integrante_id ?? "";
      this.form.inicio_cargo = this.formatDateForInput(
        registro.inicio_cargo
      );
      this.form.fin_cargo = this.formatDateForInput(
        registro.fin_cargo
      );
      this.form.periodo_habil = registro.periodo_habil ?? "";

      //limpia los documentos al cambiar de registro
      this.form.doc_nombramiento = null;
      this.form.doc_carta_reeleccion = null;
      this.form.doc_otros = null;
    },

    //restablece el formulario a sus valores iniciales
    limpiarFormulario() {
      this.form = {
        integrante_id: "",
        inicio_cargo: "",
        fin_cargo: "",
        periodo_habil: "",
        doc_nombramiento: null,
        doc_carta_reeleccion: null,
        doc_otros: null,
      };
    },

    //convierte una fecha al formato YYYY-MM-DD que usa el input date
    formatDateForInput(date) {
      if (!date) return "";

      //evita desfases de fecha por zona horaria
      if (typeof date === "string") {
        return date.substring(0, 10);
      }

      const d = new Date(date);

      const year = d.getFullYear();
      const month = String(d.getMonth() + 1).padStart(2, "0");
      const day = String(d.getDate()).padStart(2, "0");

      return `${year}-${month}-${day}`;
    },

    //calcula la fecha de culminación a partir de la fecha de inicio
    autoCalcularFin() {
      if (!this.form.inicio_cargo) return;

      const inicio = new Date(`${this.form.inicio_cargo}T00:00:00`);

      //suma 3 años a la fecha de inicio
      const fin = new Date(inicio);
      fin.setFullYear(fin.getFullYear() + 3);

      const year = fin.getFullYear();
      const month = String(fin.getMonth() + 1).padStart(2, "0");
      const day = String(fin.getDate()).padStart(2, "0");

      this.form.fin_cargo = `${year}-${month}-${day}`;

      this.calcularPeriodo();
    },

    //calcula los días del periodo hábil entre inicio y fin
    calcularPeriodo() {
      if (!this.form.inicio_cargo || !this.form.fin_cargo) return;

      const inicio = new Date(`${this.form.inicio_cargo}T00:00:00`);
      const fin = new Date(`${this.form.fin_cargo}T00:00:00`);

      const diffTime = Math.abs(fin - inicio);
      const diffDays = Math.ceil(
        diffTime / (1000 * 60 * 60 * 24)
      );

      this.form.periodo_habil = `${diffDays} días`;
    },

    //guarda el archivo seleccionado de cada documento
    handleNombramiento(e) {
      this.form.doc_nombramiento = e.target.files[0] ?? null;
    },

    handleCarta(e) {
      this.form.doc_carta_reeleccion = e.target.files[0] ?? null;
    },

    handleOtros(e) {
      this.form.doc_otros = e.target.files[0] ?? null;
    },

    //envía el formulario con los documentos adjuntos
    submitForm() {
      const data = new FormData();

      //solo se agregan los campos con valor
      Object.keys(this.form).forEach((key) => {
        if (
          this.form[key] !== null &&
          this.form[key] !== ""
        ) {
          data.append(key, this.form[key]);
        }
      });

      //la ruta depende de si es reelección o alta de periodo
      if (this.esReeleccion) {
        router.post(
          `/legalidad/${this.editData.id}/reeleccion`,
          data
        );
      } else {
        router.post(
          `/legalidad/${this.consejo.id}`,
          data
        );
      }

      //cierra el modal después de enviar
      setTimeout(() => {
        this.$emit("close");
      }, 300);
    },
  },
};
</script>

<template>
  <!-- Modal del formulario -->
  <DraggableModal v-if="show" :title="esReeleccion
      ? 'Iniciar de proceso de reelección'
      : 'Crear periodo'" max-width="max-w-md" @close="$emit('close')">
    <form @submit.prevent="submitForm">
      <!-- Integrante -->
      <label class="block mb-2 font-semibold">
        Integrante
      </label>

      <select v-model="form.integrante_id"
        class="w-full border rounded px-3 py-2 mb-3 bg-gray-100"
        :disabled="esReeleccion">
        <option value="">Seleccione</option>

        <option v-for="i in integrantes"
          :key="i.id"
          :value="i.id">
          {{ i.nombre }} {{ i.apellido }}
        </option>
      </select>

      <!-- Fechas del periodo -->
      <label class="block mb-2 font-semibold">
        Fecha de inicio
      </label>

      <input type="date" v-model="form.inicio_cargo"
        class="w-full border rounded px-3 py-2 mb-3"
        :disabled="esReeleccion"@change="autoCalcularFin"/>
      <label class="block mb-2 font-semibold">
        Fecha de culminación
      </label>

      <input type="date" v-model="form.fin_cargo" class="w-full border rounded px-3 py-2 mb-3"
        :disabled="esReeleccion" @change="calcularPeriodo"/>

      <!-- Documentos de reelección -->
      <div v-if="esReeleccion">
        <label class="block mb-2 font-semibold">
          Nombramiento (PDF)
        </label>

        <input type="file" accept="application/pdf"
          @change="handleNombramiento"
          class="w-full border rounded px-3 py-2 mb-3" required/>

        <label class="block mb-2 font-semibold">
          Carta de reelección (PDF)
        </label>

        <input type="file" accept="application/pdf" @change="handleCarta"
          class="w-full border rounded px-3 py-2 mb-3" required/>

        <label class="block mb-2 font-semibold">
          Otros documentos (PDF, opcional)
        </label>

        <input type="file" accept="application/pdf" @change="handleOtros"
          class="w-full border rounded px-3 py-2 mb-3"/>
        <p class="text-sm text-yellow-700 mt-2">
          Los documentos serán revisados por un Administrador.
        </p>

      </div>

      <!-- Botones -->
      <div class="flex justify-end mt-4">
        <button type="button" @click="$emit('close')"
          class="px-4 py-2 text-white rounded mr-2" style="background-color:#C91212;">
          Cancelar
        </button>

        <button type="submit" class="px-4 py-2 text-white rounded"
          :style="{
            backgroundColor: esReeleccion
              ? '#7A1F32'
              : '#C7A447'}">
          Guardar
        </button>
      </div>

    </form>
  </DraggableModal>
</template>
