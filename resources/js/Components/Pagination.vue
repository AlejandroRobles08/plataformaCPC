<template>
  <div v-if="links.length > 3">
    <div class="-mb-1 flex flex-wrap">
      <template v-for="(link, key) in links">
        <div v-if="link.url === null" :key="key" class="mr-1 mb-1 rounded border px-2 py-1
        text-sm leading-4 text-gray-400" v-html="link.label" />
        <!-- Paginación en el navegador (ej. dentro de un modal): cambia de página sin recargar -->
        <button v-else-if="link.pagina" :key="`pagina-${key}`" type="button" :class="[claseEnlace, claseActivo(link)]"
          @click="emit('cambiar-pagina', link.pagina)" v-html="link.label" />
        <Link v-else :key="key + 1" :class="[claseEnlace, claseActivo(link)]"
        :href="link.url" v-html="link.label" />
      </template>
    </div>
  </div>
</template>

<script setup>
import { Link } from "@inertiajs/vue3";

defineProps({
  links: Array,
});

const emit = defineEmits(["cambiar-pagina"]);

const claseEnlace = "mr-1 mb-1 rounded border px-2 py-1 text-sm leading-4 hover:bg-white focus:border-red-500 focus:text-red-500";
const claseActivo = (link) => ({ "text-white bg-red-600 hover:bg-red-700": link.active });
</script>
