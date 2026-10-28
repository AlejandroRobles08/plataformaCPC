<script setup>
import SidebarMenu from './SidebarMenu.vue'
import { Link, usePage } from '@inertiajs/vue3';
import { onMounted, onBeforeUnmount } from 'vue'

const page = usePage()

// md de Tailwind: a partir de este ancho se muestra el sidebar de escritorio
const desktopQuery = window.matchMedia('(min-width: 768px)')

const closeMenu = () => {
  page.props.showingMobileMenu = false
}

const handleDesktopChange = (event) => {
  if (event.matches) {
    closeMenu()
  }
}

const handleKeydown = (event) => {
  if (event.key === 'Escape') {
    closeMenu()
  }
}

onMounted(() => {
  desktopQuery.addEventListener('change', handleDesktopChange)
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  desktopQuery.removeEventListener('change', handleDesktopChange)
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <transition
      enter-active-class="transition ease-in-out duration-150"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in-out duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0">
    <div v-show="$page.props.showingMobileMenu" @click="closeMenu"
         class="fixed inset-0 z-10 bg-black bg-opacity-50 md:hidden"></div>
  </transition>
  <transition
      enter-active-class="transition ease-in-out duration-150"
      enter-from-class="opacity-0 transform -translate-x-20"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in-out duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0 transform -translate-x-20">
    <aside v-show="$page.props.showingMobileMenu"
           class="fixed inset-y-0 z-20 flex-shrink-0 w-64 mt-16 overflow-y-auto bg-gray-800 md:hidden">
      <div class="py-4 text-white">
        <Link class="ml-6 text-lg font-bold text-white" :href="route('dashboard')">
          IMPLAN
        </Link>

        <SidebarMenu />
      </div>
    </aside>
  </transition>
</template>
