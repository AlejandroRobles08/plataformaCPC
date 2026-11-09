<script setup>
import { Link } from '@inertiajs/vue3'

// Layout de las páginas abiertas a la ciudadanía (sin inicio de sesión)
</script>

<template>
  <div class="min-h-screen flex flex-col bg-gray-50">
    <header class="bg-white border-b border-gray-200">
      <div class="max-w-5xl mx-auto flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <div class="flex items-center gap-3 min-w-0">
          <img src="/images/logo-implan.png" alt="IMPLAN" class="h-10 w-auto shrink-0" />
          <span class="text-sm font-semibold text-gray-700 leading-tight">
            Consejos de Participación Ciudadana
          </span>
        </div>
        <Link :href="route('login')" class="shrink-0 text-sm font-medium text-gray-600 hover:text-gray-900 underline">
          Iniciar sesión
        </Link>
      </div>
    </header>

    <main class="flex-1 w-full max-w-5xl mx-auto px-4 py-8 sm:px-6">
      <slot />
    </main>

    <footer class="py-6 text-center text-xs text-gray-500">
      Plataforma CPC
    </footer>
  </div>
</template>
