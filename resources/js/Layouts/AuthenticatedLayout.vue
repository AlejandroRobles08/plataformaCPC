<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import Navigation from './Navigation.vue'
import TopMenu from './TopMenu.vue'
import NavigationMobile from './NavigationMobile.vue'
import PrivacyModal from '@/Components/PrivacyModal.vue'
import PasswordReminderModal from '@/Components/PasswordReminderModal.vue'
import LoadingScreen from '@/Components/LoadingScreen.vue'

const showPrivacyModal = ref(true)
const showPasswordModal = ref(false)

const handlePageShow = (event) => {
  if (event.persisted) {
    window.location.reload()
  }
}

const privacyAccepted = () => {
  showPrivacyModal.value = false
  showPasswordModal.value = true
}

onMounted(() => {
  window.addEventListener('pageshow', handlePageShow)
})

onBeforeUnmount(() => {
  window.removeEventListener('pageshow', handlePageShow)
})
</script>

<template>
  <PrivacyModal v-if="showPrivacyModal" @accepted="privacyAccepted" />
  <PasswordReminderModal v-if="showPasswordModal" />

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
          <LoadingScreen />
        </div>
      </div>
    </div>
  </div>
</template>