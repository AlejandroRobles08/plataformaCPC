<script setup>
import SidebarMenu from './SidebarMenu.vue'
import { Link } from '@inertiajs/vue3';
</script>

<template>
  <aside class="z-20 hidden w-64 overflow-y-auto bg-gray-800 md:block flex-shrink-0">
    <div class="py-4 text-white">
      <Link class="ml-6 text-lg font-bold text-white" :href="route('dashboard')">
        IMPLAN
      </Link>

      <SidebarMenu />
    </div>
  </aside>
</template>
