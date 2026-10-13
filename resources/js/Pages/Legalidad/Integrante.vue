<script>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Link, useForm } from "@inertiajs/vue3";

export default {
  components: { AuthenticatedLayout, Link },

  //datos enviados desde el controlador
  props: {
    consejo: { type: Object, required: true },
    integrantes: { type: Array, default: () => [] },
    registros: { type: Array, default: () => [] },
  },

  //formulario con los documentos de la solicitud de reelección
  setup() {
    const form = useForm({
      doc_nombramiento: null,
      doc_carta_reeleccion: null,
      doc_otros: null,
    });

    return { form };
  },

  computed: {
    //periodo más reciente de la persona integrante
    periodo() {
      return this.registros[0] ?? null;
    },

    integrante() {
      return this.periodo?.integrante ?? this.integrantes[0] ?? {};
    },

    //nombre completo o, en su defecto, el nombre del usuario
    nombre() {
      const completo = [
        this.integrante.nombre,
        this.integrante.apellido,
      ].filter(Boolean).join(" ");

      return completo || this.$page.props.auth?.user?.name || "Integrante";
    },

    puesto() {
      return this.integrante.puesto || "Integrante";
    },

    //estatus de la reelección del periodo actual
    estado() {
      return this.periodo?.estatus_reeleccion ?? "sin_periodo";
    },

    //texto a mostrar según el estatus
    estadoTexto() {
      const estados = {
        aprobado: "Aprobado",
        rechazado: "Rechazado",
        pendiente: "Pendiente",
        sin_periodo: "Sin periodo registrado",
      };

      return estados[this.estado] ?? estados.sin_periodo;
    },

    inicio() {
      return this.periodo?.inicio_cargo ?? null;
    },

    fin() {
      return this.periodo?.fin_cargo ?? null;
    },

    //indica si la fecha de fin ya pasó
    vencido() {
      return this.fin ? this.fecha(this.fin) < this.fecha(new Date()) : false;
    },

    //indica si al periodo le quedan 4 meses o menos
    porVencer() {
      if (!this.fin || this.vencido) return false;

      const limite = this.fecha(new Date());
      limite.setMonth(limite.getMonth() + 4);

      return this.fecha(this.fin) <= limite;
    },

    //color de la tarjeta del periodo según su vigencia
    colorPeriodo() {
      if (!this.periodo) return "";
      if (this.vencido) return "border-red-400 bg-red-50 text-red-700";
      if (this.porVencer) return "border-amber-400 bg-amber-50 text-amber-700";
      if (this.estado === "aprobado") return "border-emerald-500 bg-emerald-50 text-emerald-700";

      return "";
    },

    //calcula el tiempo restante del periodo en formato AA:MM:DD
    restante() {
      if (!this.fin) return null;

      const hoy = this.fecha(new Date());
      const fin = this.fecha(this.fin);

      if (fin < hoy) {
        return { texto: "Vencido", anios: 0, meses: 0, dias: 0 };
      }

      let anios = fin.getFullYear() - hoy.getFullYear();
      let meses = fin.getMonth() - hoy.getMonth();
      let dias = fin.getDate() - hoy.getDate();

      //ajusta días y meses negativos
      if (dias < 0) {
        meses--;
        dias += new Date(fin.getFullYear(), fin.getMonth(), 0).getDate();
      }

      if (meses < 0) {
        anios--;
        meses += 12;
      }

      const pad = (n) => String(n).padStart(2, "0");

      return {
        texto: `${pad(anios)}:${pad(meses)}:${pad(dias)}`,
        anios,
        meses,
        dias,
      };
    },

    //la solicitud ya se envió y está en espera de validación
    solicitudEnviada() {
      return !!this.periodo?.fecha_inicio_reeleccion
        && this.estado === "pendiente";
    },

    //un rechazo o un periodo vencido cierran la reelección de forma definitiva
    reeleccionDisponible() {
      return !!this.periodo
        && !this.periodo.ya_reelegido
        && !this.vencido
        && !this.solicitudEnviada
        && this.estado !== "aprobado"
        && this.estado !== "rechazado";
    },

    //fechas estimadas del periodo de reelección
    reeleccion() {
      if (!this.fin) return null;

      const inicio = this.fecha(this.fin);
      inicio.setDate(inicio.getDate() + 1);

      const fin = new Date(inicio);
      fin.setFullYear(fin.getFullYear() + 3);

      return { inicio, fin };
    },
  },

  methods: {
    //convierte el valor a fecha local sin hora para evitar desfases
    fecha(valor) {
      if (!valor) return new Date(0);

      const fecha = valor instanceof Date
        ? valor
        : new Date(`${String(valor).substring(0, 10)}T00:00:00`);

      return new Date(fecha.getFullYear(), fecha.getMonth(), fecha.getDate());
    },

    //formatea la fecha a DD/MM/AAAA
    formatoFecha(valor) {
      if (!valor) return "No disponible";

      const [anio, mes, dia] = String(valor).substring(0, 10).split("-");
      return `${dia}/${mes}/${anio}`;
    },

    anio(valor) {
      return valor ? String(valor).substring(0, 4) : "----";
    },

    //guarda el archivo seleccionado en el campo indicado
    seleccionarArchivo(evento, campo) {
      this.form[campo] = evento.target.files[0] ?? null;
    },

    //envía la solicitud de reelección con sus documentos
    enviarSolicitud() {
      if (!this.periodo || this.form.processing) return;

      this.form.post(route("legalidad.reeleccion", this.periodo.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => this.form.reset(),
      });
    },
  },
};
</script>


<template>
  <AuthenticatedLayout>
    <div class="px-4 py-6 sm:px-6">
      <div class="mx-auto max-w-7xl space-y-6">
        <!-- Navegación -->
        <div>
          <Link :href="route('consejos.legalidad', consejo.id)"
            class="text-sm opacity-75 hover:opacity-100 hover:underline">
            ← Volver a Consejos de Participación Ciudadana
          </Link>
        </div>

        <!-- Encabezado -->
        <header>
          <h1 class="text-3xl font-bold">Periodo en el cargo</h1>
          <p class="mt-2 text-xl opacity-75">{{ consejo.nombre }}</p>
        </header>

        <!-- Mi información -->
        <section class="space-y-5 rounded-xl border p-4 shadow sm:p-6">
          <h2 class="text-lg font-semibold">♙ Mi información</h2>

          <div class="grid gap-5 rounded-xl border p-5 md:grid-cols-2">
            <!-- Datos de la persona integrante -->
            <div class="space-y-4">
              <div class="flex flex-wrap items-center gap-3">
                <h3 class="text-2xl font-bold">{{ nombre }}</h3>
                <span class="rounded border px-3 py-1 text-sm">{{ puesto }}</span>
              </div>

              <div class="grid gap-3 sm:grid-cols-[150px_1fr]">
                <span class="opacity-70">Periodo actual:</span>
                <span>{{ inicio ? `Primer periodo (${anio(inicio)} - ${anio(fin)})` : "Sin periodo registrado" }}</span>
              </div>
            </div>

            <!-- Estatus actual -->
            <div class="border-t pt-4 md:border-l md:border-t-0 md:pl-6 md:pt-0">
              <p class="mb-3 text-sm opacity-70">Estatus actual</p>

              <div v-if="solicitudEnviada"
                class="inline-flex items-center gap-3 rounded-xl border border-amber-400 px-4 py-3 text-amber-600">
                <span class="text-2xl">●</span>
                <div>
                  <strong class="block">Pendiente</strong>
                  <small>En espera de validación</small>
                </div>
              </div>

              <div v-else-if="estado === 'rechazado'"
                class="rounded-lg border border-red-400 bg-red-50 p-4 text-red-700">
                <strong class="block">Solicitud negada.</strong>
                <p class="mt-1 text-sm">Tu solicitud de reelección no fue aprobada.</p>
                <p v-if="vencido" class="mt-1 text-sm font-semibold">El periodo en el cargo ha concluido.</p>
                <div v-if="periodo?.motivo_rechazo" class="mt-3 border-t border-red-300 pt-3 text-sm">
                  <span class="font-semibold">Motivo:</span>
                  <p class="mt-1 whitespace-pre-line">{{ periodo.motivo_rechazo }}</p>
                </div>
              </div>

              <div v-else-if="vencido"
                class="rounded-lg border border-red-400 bg-red-50 p-4 text-red-700">
                <strong class="block">Periodo vencido</strong>
                <p class="mt-1 text-sm">
                  {{ periodo?.ya_reelegido
                    ? "Concluyó tu primer periodo. No es posible otra reelección consecutiva."
                    : "El periodo inicial concluyó sin que se solicitara la reelección." }}
                </p>
              </div>

              <div v-else-if="estado === 'aprobado'"
                class="inline-flex items-center gap-3 rounded-xl border border-emerald-500 px-4 py-3 text-emerald-600">
                <span class="text-2xl">✓</span>
                <div>
                  <strong class="block">Aprobado</strong>
                  <small>Periodo en curso</small>
                </div>
              </div>

              <!-- Solicitud de reelección -->
              <form v-if="reeleccionDisponible" class="mt-3 space-y-4"
                @submit.prevent="enviarSolicitud">
                <p class="text-sm opacity-75">
                  Adjunta los documentos requeridos para solicitar tu reelección.
                </p>

                <div>
                  <label class="mb-1 block text-sm font-medium">Documento de nombramiento *</label>
                  <input type="file" accept=".pdf,application/pdf" required
                    class="block w-full rounded-lg border p-2 text-sm file:mr-3 file:rounded file:border file:px-3 file:py-2"
                    @change="seleccionarArchivo($event, 'doc_nombramiento')" />
                  <p v-if="form.errors.doc_nombramiento" class="mt-1 text-sm text-red-600">
                    {{ form.errors.doc_nombramiento }}
                  </p>
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium">Carta de reelección *</label>
                  <input type="file" accept=".pdf,application/pdf" required
                    class="block w-full rounded-lg border p-2 text-sm file:mr-3 file:rounded file:border file:px-3 file:py-2"
                    @change="seleccionarArchivo($event, 'doc_carta_reeleccion')" />
                  <p v-if="form.errors.doc_carta_reeleccion" class="mt-1 text-sm text-red-600">
                    {{ form.errors.doc_carta_reeleccion }}
                  </p>
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium">Otros documentos (opcional)</label>
                  <input type="file" accept=".pdf,application/pdf"
                    class="block w-full rounded-lg border p-2 text-sm file:mr-3 file:rounded file:border file:px-3 file:py-2"
                    @change="seleccionarArchivo($event, 'doc_otros')" />
                  <p v-if="form.errors.doc_otros" class="mt-1 text-sm text-red-600">
                    {{ form.errors.doc_otros }}
                  </p>
                </div>

                <p class="text-xs opacity-70">Formato PDF. Tamaño máximo de 5 MB por archivo.</p>

                <button type="submit" :disabled="form.processing"
                  class="w-full rounded-lg bg-[#7A1F32] px-4 py-3 font-semibold text-white transition hover:bg-[#922840] disabled:cursor-not-allowed disabled:opacity-50">
                  {{ form.processing ? "Enviando solicitud..." : "Enviar solicitud de reelección" }}
                </button>
              </form>

              <div v-else-if="estado === 'sin_periodo'"
                class="rounded-lg border p-3 text-sm">
                No tienes un periodo registrado.
              </div>
            </div>
          </div>

          <!-- Fechas y tiempo restante -->
          <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border p-5">
              <p class="text-sm opacity-70">▦ Inicio de periodo</p>
              <p class="mt-2 text-xl font-bold">{{ formatoFecha(inicio) }}</p>
            </div>

            <div class="rounded-xl border p-5">
              <p class="text-sm opacity-70">▦ Fin de periodo</p>
              <p class="mt-2 text-xl font-bold">{{ formatoFecha(fin) }}</p>
            </div>

            <div class="rounded-xl border p-5">
              <p class="text-sm opacity-70">◷ Periodo hábil restante</p>
              <p class="mt-2 text-2xl font-bold" :class="{ 'text-red-600': vencido }">
                {{ restante?.texto ?? "No disponible" }}
              </p>
              <p v-if="restante && !vencido" class="mt-2 text-sm opacity-75">
                {{ restante.anios }} años · {{ restante.meses }} meses · {{ restante.dias }} días
              </p>
            </div>
          </div>

          <!-- Línea de periodos -->
          <section class="space-y-4">
            <h2 class="text-xl font-bold">Periodos activos</h2>
            <div class="relative border-l pl-5">
              <article class="rounded-xl border p-4" :class="colorPeriodo">
                <div class="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <h3 class="font-bold">
                      Periodo actual ({{ anio(inicio) }} - {{ anio(fin) }})
                    </h3>
                    <p class="mt-1 text-sm opacity-75">
                      {{ periodo?.ya_reelegido ? "Periodo de reelección" : "Primer periodo" }}
                    </p>
                  </div>
                  <span class="rounded-lg border border-current px-3 py-2 text-sm">
                    {{ !periodo ? "Sin registrar" : vencido ? "Concluido" : porVencer ? "Próximo a vencer" : "En curso / Activo" }}
                  </span>
                </div>
                <p class="mt-3 text-sm opacity-75">
                  {{ formatoFecha(inicio) }} - {{ formatoFecha(fin) }}
                </p>
              </article>
            </div>
          </section>

          <!-- Información importante -->
          <div class="rounded-xl border p-5 bg-gray-300">
            <h3 class="font-bold">ⓘ Información importante</h3>
            <p class="mt-2 text-sm leading-relaxed opacity-75">
              El cargo permite una sola reelección consecutiva, además del periodo ordinario inicial.
              Al concluir el segundo periodo no se permitirá otra reelección consecutiva.
            </p>
          </div>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
