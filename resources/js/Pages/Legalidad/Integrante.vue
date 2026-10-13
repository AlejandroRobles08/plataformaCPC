
<script>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Link, useForm } from "@inertiajs/vue3";

export default {
  components: { AuthenticatedLayout, Link },

  props: {
    consejo: { type: Object, required: true },
    integrantes: { type: Array, default: () => [] },
    registros: { type: Array, default: () => [] },
  },

  setup() {
    const form = useForm({
      doc_nombramiento: null,
      doc_carta_reeleccion: null,
      doc_otros: null,
    });

    return { form };
  },

  computed: {
    periodo() {
      return this.registros[0] ?? null;
    },

    integrante() {
      return this.periodo?.integrante ?? this.integrantes[0] ?? {};
    },

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

    estado() {
      return this.periodo?.estatus_reeleccion ?? "sin_periodo";
    },

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

    vencido() {
      return this.fin ? this.fecha(this.fin) < this.fecha(new Date()) : false;
    },

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

    solicitudEnviada() {
      return !!this.periodo?.fecha_inicio_reeleccion
        && this.estado === "pendiente";
    },

    reeleccionDisponible() {
      return !!this.periodo
        && !this.periodo.ya_reelegido
        && !this.vencido
        && !this.solicitudEnviada
        && this.estado !== "aprobado";
    },

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
    fecha(valor) {
      if (!valor) return new Date(0);

      const fecha = valor instanceof Date
        ? valor
        : new Date(`${String(valor).substring(0, 10)}T00:00:00`);

      return new Date(fecha.getFullYear(), fecha.getMonth(), fecha.getDate());
    },

    formatoFecha(valor) {
      if (!valor) return "No disponible";

      const [anio, mes, dia] = String(valor).substring(0, 10).split("-");
      return `${dia}/${mes}/${anio}`;
    },

    anio(valor) {
      return valor ? String(valor).substring(0, 4) : "----";
    },

    iniciales(nombre) {
      return nombre.split(" ").filter(Boolean).slice(0, 2)
        .map((palabra) => palabra[0]).join("").toUpperCase();
    },

    seleccionarArchivo(evento, campo) {
      this.form[campo] = evento.target.files[0] ?? null;
    },

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
    <div class="min-h-screen bg-slate-950 px-4 py-6 text-slate-100 sm:px-6">
      <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <Link :href="route('consejos.legalidad', consejo.id)"
            class="text-sm text-slate-300 hover:text-white">
            ← Volver a Consejos de Participación Ciudadana
          </Link>

          <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-700 font-bold">
              {{ iniciales(nombre) }}
            </div>
            <div>
              <p class="font-semibold">{{ nombre }}</p>
              <p class="text-sm text-slate-400">Integrante</p>
            </div>
          </div>
        </div>

        <header>
          <h1 class="text-3xl font-bold">Periodo en el cargo</h1>
          <p class="mt-2 text-xl text-slate-300">{{ consejo.nombre }}</p>
        </header>

        <section class="space-y-5 rounded-xl border border-slate-800 bg-slate-900/70 p-4 shadow-xl sm:p-6">
          <h2 class="text-lg font-semibold">♙ Mi información</h2>

          <div class="grid gap-5 rounded-xl border border-slate-800 bg-slate-950/50 p-5 md:grid-cols-2">
            <div class="space-y-4">
              <div class="flex flex-wrap items-center gap-3">
                <h3 class="text-2xl font-bold">{{ nombre }}</h3>
                <span class="rounded bg-slate-700 px-3 py-1 text-sm">{{ puesto }}</span>
              </div>

              <div class="grid gap-3 sm:grid-cols-[150px_1fr]">
                <span class="text-slate-400">Periodo actual:</span>
                <span>{{ inicio ? `Periodo ordinario (${anio(inicio)} - ${anio(fin)})` : "Sin periodo registrado" }}</span>
              </div>
            </div>

            <div class="border-t border-slate-800 pt-4 md:border-l md:border-t-0 md:pl-6 md:pt-0">
              <p class="mb-3 text-sm text-slate-400">Estatus actual</p>

              <div v-if="solicitudEnviada"
                class="inline-flex items-center gap-3 rounded-xl bg-amber-900/50 px-4 py-3 text-amber-300">
                <span class="text-2xl">●</span>
                <div>
                  <strong class="block">Pendiente</strong>
                  <small>En espera de validación</small>
                </div>
              </div>

              <div v-else-if="estado === 'aprobado'"
                class="inline-flex items-center gap-3 rounded-xl bg-emerald-900/60 px-4 py-3 text-emerald-300">
                <span class="text-2xl">✓</span>
                <div>
                  <strong class="block">Aprobado</strong>
                  <small>Periodo en curso</small>
                </div>
              </div>

              <div v-else-if="estado === 'rechazado'"
                class="mb-4 rounded-lg bg-red-900/40 p-3 text-red-300">
                <strong>Solicitud rechazada</strong>
                <p class="mt-1 text-sm">Puedes enviar una nueva solicitud.</p>
              </div>

              <form v-if="reeleccionDisponible" class="mt-3 space-y-4"
                @submit.prevent="enviarSolicitud">
                <p class="text-sm text-slate-300">
                  Adjunta los documentos requeridos para solicitar tu reelección.
                </p>

                <div>
                  <label class="mb-1 block text-sm font-medium">Documento de nombramiento *</label>
                  <input type="file" accept=".pdf,application/pdf" required
                    class="block w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-slate-300 file:mr-3 file:rounded file:border-0 file:bg-slate-700 file:px-3 file:py-2 file:text-white"
                    @change="seleccionarArchivo($event, 'doc_nombramiento')" />
                  <p v-if="form.errors.doc_nombramiento" class="mt-1 text-sm text-red-400">
                    {{ form.errors.doc_nombramiento }}
                  </p>
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium">Carta de reelección *</label>
                  <input type="file" accept=".pdf,application/pdf" required
                    class="block w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-slate-300 file:mr-3 file:rounded file:border-0 file:bg-slate-700 file:px-3 file:py-2 file:text-white"
                    @change="seleccionarArchivo($event, 'doc_carta_reeleccion')" />
                  <p v-if="form.errors.doc_carta_reeleccion" class="mt-1 text-sm text-red-400">
                    {{ form.errors.doc_carta_reeleccion }}
                  </p>
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium">Otros documentos (opcional)</label>
                  <input type="file" accept=".pdf,application/pdf"
                    class="block w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-slate-300 file:mr-3 file:rounded file:border-0 file:bg-slate-700 file:px-3 file:py-2 file:text-white"
                    @change="seleccionarArchivo($event, 'doc_otros')" />
                  <p v-if="form.errors.doc_otros" class="mt-1 text-sm text-red-400">
                    {{ form.errors.doc_otros }}
                  </p>
                </div>

                <p class="text-xs text-slate-400">Formato PDF. Tamaño máximo de 5 MB por archivo.</p>

                <button type="submit" :disabled="form.processing"
                  class="w-full rounded-lg bg-[#7A1F32] px-4 py-3 font-semibold text-white transition hover:bg-[#922840] disabled:cursor-not-allowed disabled:opacity-50">
                  {{ form.processing ? "Enviando solicitud..." : "Enviar solicitud de reelección" }}
                </button>
              </form>

              <div v-else-if="estado === 'sin_periodo'"
                class="rounded-lg bg-slate-800 p-3 text-sm text-slate-300">
                No tienes un periodo registrado.
              </div>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-5">
              <p class="text-sm text-slate-400">▦ Inicio de periodo</p>
              <p class="mt-2 text-xl font-bold">{{ formatoFecha(inicio) }}</p>
            </div>

            <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-5">
              <p class="text-sm text-slate-400">▦ Fin de periodo</p>
              <p class="mt-2 text-xl font-bold">{{ formatoFecha(fin) }}</p>
            </div>

            <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-5">
              <p class="text-sm text-slate-400">◷ Periodo hábil restante</p>
              <p class="mt-2 text-2xl font-bold" :class="vencido ? 'text-red-400' : 'text-emerald-300'">
                {{ restante?.texto ?? "No disponible" }}
              </p>
              <p v-if="restante && !vencido" class="mt-2 text-sm text-slate-300">
                {{ restante.anios }} años · {{ restante.meses }} meses · {{ restante.dias }} días
              </p>
            </div>
          </div>
    
        <!-- Línea de periodos -->
        <section class="space-y-4">
        <h2 class="text-xl font-bold">Periodos activos</h2>
          <div class="relative border-l border-slate-700 pl-5">
            <article class="rounded-xl border p-4" :class="vencido
        ? 'border-slate-700 bg-slate-900/50'
        : 'border-emerald-700 bg-emerald-950/30'">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="font-bold">
                        Periodo actual ({{ anio(inicio) }} - {{ anio(fin) }})
                    </h3>
                <p class="mt-1 text-sm text-slate-300">
                    {{ periodo?.ya_reelegido ? "Periodo de reelección" : "Periodo ordinario" }}
                </p>
            </div>
        <span class="rounded-lg px-3 py-2 text-sm" :class="vencido
            ? 'bg-slate-800 text-slate-300'
            : 'bg-emerald-900 text-emerald-300'">
          {{ !periodo ? "Sin registrar" : vencido ? "Concluido" : "En curso / Activo" }}
        </span>
      </div>
        <p class="mt-3 text-sm text-slate-300">
        {{ formatoFecha(inicio) }} - {{ formatoFecha(fin) }}
       </p>
      </article>
     </div>
    </section>
    
          <div class="rounded-xl border border-blue-900 bg-blue-950/30 p-5">
            <h3 class="font-bold text-blue-300">ⓘ Información importante</h3>
            <p class="mt-2 text-sm leading-relaxed text-slate-300">
              El cargo permite una sola reelección consecutiva, además del periodo ordinario inicial.
              Al concluir el segundo periodo no se permitirá otra reelección consecutiva.
            </p>
          </div>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
