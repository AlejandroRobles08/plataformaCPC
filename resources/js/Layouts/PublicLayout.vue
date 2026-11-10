<script setup>
// Layout de las páginas abiertas a la ciudadanía (sin inicio de sesión)
</script>

<template>
  <div class="min-h-screen flex flex-col bg-gray-50">
    <header class="bg-white border-b border-gray-200">
      <div class="max-w-5xl mx-auto flex items-center gap-5 px-4 py-5 sm:px-6">
        <img src="/images/logo-implan.png" alt="IMPLAN" class="h-16 sm:h-20 w-auto shrink-0" />
        <span class="text-lg sm:text-xl font-semibold text-gray-700 leading-tight">
          Consejos de Participación Ciudadana
        </span>
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
