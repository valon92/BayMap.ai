<template>
  <div class="min-h-screen min-h-[100dvh] flex flex-col overflow-x-clip max-w-full">
    <AnimatedBackground />
    <AppHeader />
    <main class="flex-1 relative z-10 overflow-x-clip max-w-full min-w-0">
      <router-view v-slot="{ Component }">
        <transition name="fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </main>
    <AppFooter />
    <ScrollToTop />
  </div>
</template>

<script setup>
import AnimatedBackground from './components/AnimatedBackground.vue';
import AppHeader from './components/AppHeader.vue';
import AppFooter from './components/AppFooter.vue';
import ScrollToTop from './components/ScrollToTop.vue';
</script>

<style>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
