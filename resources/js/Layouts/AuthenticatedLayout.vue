<script setup>
import { onMounted, onBeforeUnmount } from 'vue'
import Navigation from './Navigation.vue'
import TopMenu from './TopMenu.vue'
import NavigationMobile from './NavigationMobile.vue'
import PasswordReminderModal from '@/Components/PasswordReminderModal.vue'


const handlePageShow = (event) => {
  if (event.persisted) {
    window.location.reload()
  }
}

onMounted(() => {
  window.addEventListener('pageshow', handlePageShow)
})

onBeforeUnmount(() => {
  window.removeEventListener('pageshow', handlePageShow)
})
</script>

<template>
  <!-- El aviso de privacidad ya se aceptó en el login; aquí solo el cambio de contraseña temporal -->
  <PasswordReminderModal />

  <div>
    <div class="flex h-screen bg-gray-50">
      <Navigation />
      <NavigationMobile />

      <div class="flex flex-col flex-1 w-full">
        <TopMenu />
        <div class="relative flex-1 min-h-0">
          <main class="h-full overflow-y-auto">
            <div class="container px-6 mx-auto grid">
              <h2 class="my-6 text-2xl font-semibold text-gray-700">
                <slot name="header" />
              </h2>
              <slot />
            </div>
          </main>
        </div>
      </div>
    </div>
  </div>
</template>