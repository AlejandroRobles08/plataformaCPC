<template>
  <nav v-if="links.length > 3" aria-label="Paginación">
    <div class="-mb-1 flex flex-wrap">
      <template v-for="(link, key) in links">
        <span v-if="link.url === null" :key="key" :class="[claseBase, claseDeshabilitado]"
          aria-disabled="true" v-html="link.label" />
        <!-- Paginación en el navegador (ej. dentro de un modal): cambia de página sin recargar -->
        <button v-else-if="link.pagina" :key="`pagina-${key}`" type="button"
          :class="[claseBase, link.active ? claseActivo : claseInactivo]"
          :aria-current="link.active ? 'page' : undefined"
          @click="emit('cambiar-pagina', link.pagina)" v-html="link.label" />
        <Link v-else :key="key + 1" :class="[claseBase, link.active ? claseActivo : claseInactivo]"
          :aria-current="link.active ? 'page' : undefined"
          :href="link.url" v-html="link.label" />
      </template>
    </div>
  </nav>
</template>

<script setup>
import { Link } from "@inertiajs/vue3";

defineProps({
  links: Array,
});

const emit = defineEmits(["cambiar-pagina"]);

// Estructura común. El anillo de foco es rojo (marca) para evitar el contorno azul por defecto del navegador.
const claseBase =
  "mr-1 mb-1 inline-flex min-w-[2rem] items-center justify-center rounded-md border px-2.5 py-1.5 " +
  "text-sm leading-4 transition-colors duration-150 " +
  "focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500/60 " +
  "focus-visible:ring-offset-1 focus-visible:ring-offset-white dark:focus-visible:ring-offset-zinc-900";

// Página normal: neutros cálidos en claro, zinc en oscuro.
const claseInactivo =
  "border-stone-300 bg-white text-stone-700 hover:border-red-300 hover:bg-red-50 hover:text-red-700 " +
  "dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 " +
  "dark:hover:border-red-400/50 dark:hover:bg-red-500/10 dark:hover:text-red-300";

// Página actual: rojo de marca, algo más suave en oscuro para no deslumbrar.
const claseActivo =
  "border-red-600 bg-red-600 font-semibold text-white shadow-sm hover:bg-red-700 " +
  "dark:border-red-500 dark:bg-red-500/90 dark:hover:bg-red-500";

// Anterior/Siguiente sin destino.
const claseDeshabilitado =
  "cursor-not-allowed border-stone-200 bg-stone-50 text-stone-400 " +
  "dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-600";
</script>
