<script setup>
// Instructivo de la semaforización de documentos (Archivo digital)
defineProps({ esIntegrante: Boolean })

const leyenda = [
  { color: 'verde', clase: 'bg-green-500', texto: 'Ya cumple con su expediente: todos sus documentos están aprobados.' },
  { color: 'amarillo', clase: 'bg-yellow-400', texto: 'Tiene al menos 1 documento aprobado.' },
  { color: 'rojo', clase: 'bg-red-500', texto: 'No tiene ningún documento aprobado.' },
]
</script>

<template>
  <div class="rounded-lg border border-gray-200 p-4 text-sm text-gray-600">
    <p class="mb-2 font-semibold">Semaforización de documentos</p>
    <ul class="space-y-1">
      <li v-for="s in leyenda" :key="s.color" class="flex items-start gap-2">
        <span class="mt-1 h-3 w-3 shrink-0 rounded-full" :class="s.clase"></span>
        <span><strong class="capitalize">{{ s.color }}:</strong> {{ s.texto }}</span>
      </li>
    </ul>
  </div>
</template>
