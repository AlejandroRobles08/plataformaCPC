<script setup>
import Dropdown from '@/Components/Dropdown.vue'
import DropdownLink from '@/Components/DropdownLink.vue'
import { router } from '@inertiajs/vue3';
import { BellAlertIcon, Cog6ToothIcon, ArrowLeftStartOnRectangleIcon } from '@heroicons/vue/24/solid';

const logout = () => {
  router.post(route('logout'))
}
</script>

<template>
  <header class="z-10 py-4 bg-white shadow-md">
    <div class="container flex justify-between items-center px-6 mx-auto h-full text-gray-600 md:justify-end">
      <!-- Mobile hamburger -->
      <button @click="$page.props.showingMobileMenu = !$page.props.showingMobileMenu"
        class="p-1 mr-5 -ml-1 rounded-md md:hidden focus:outline-none focus:shadow-outline-purple" aria-label="Menu">
        <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd"
            d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
            clip-rule="evenodd"></path>
        </svg>
      </button>

      <Dropdown>
        <template #trigger>
          <button class="align-middle rounded-full focus:shadow-outline-black focus:outline-none" aria-label="Account"
            aria-haspopup="true">
            {{ $page.props.auth.user.name }}
          </button>
        </template>

        <template #content>
          <DropdownLink :href="route('profile.edit')">
            <template #icon>
              <Cog6ToothIcon class="w-4 h-4 mr-3" />
              Perfil
            </template>
          </DropdownLink>

          <button @click="" class="inline-flex items-center w-full px-4 py-2 
            text-sm text-left text-gray-700 hover:bg-gray-100 transition">
            <BellAlertIcon class="w-4 h-4 mr-3"/>
            Notificaciones
          </button>

          <button @click="logout"
            class="inline-flex items-center w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100 transition">
            <ArrowLeftStartOnRectangleIcon class="w-4 h-4 mr-3"/>
            Cerrar sesión
          </button>
        </template>  
      </Dropdown>

    </div>
  </header>
</template>
