<script setup>
// Instructivo de la semaforización de asistencias (Asistencias)
defineProps({ esIntegrante: Boolean })

const leyenda = [
  { color: 'verde', clase: 'bg-green-500', texto: 'No se registran inasistencias.' },
  { color: 'amarillo', clase: 'bg-yellow-400', texto: 'Se registran inasistencias, sin acumular 3 faltas sin justificar.' },
  { color: 'rojo', clase: 'bg-red-500', texto: 'Se acumulan 3 faltas sin justificar, sean o no seguidas, en el mismo mes o en meses diferentes. Esta condición es causa de baja directa del sistema.' },
]
</script>

<template>
  <div class="rounded-lg border border-gray-200 p-4 text-sm text-gray-600">
    <p class="mb-2 font-semibold">Semaforización de asistencias</p>
    <ul class="space-y-1">
      <li v-for="s in leyenda" :key="s.color" class="flex items-start gap-2">
        <span class="mt-1 h-3 w-3 shrink-0 rounded-full" :class="s.clase"></span>
        <span><strong class="capitalize">{{ s.color }}:</strong> {{ s.texto }}</span>
      </li>
    </ul>
    <p class="mt-2">
      {{ esIntegrante
        ? 'Si usted acumula 3 faltas sin justificar, dejará de estar disponible para el pase de lista hasta que su justificante sea enviado y validado; sus inasistencias se seguirán registrando.'
        : 'Cuando un integrante acumule 3 faltas sin justificar, aparecerá marcado en rojo y sin pase de lista hasta que su justificante sea enviado y validado; sus inasistencias se seguirán registrando.' }}
    </p>
  </div>
</template>
