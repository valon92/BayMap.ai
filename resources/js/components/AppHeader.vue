<template>
  <header class="relative z-20 px-4 sm:px-6 lg:px-8 py-5">
    <div class="max-w-6xl mx-auto flex items-center justify-between">
      <router-link to="/" class="header-brand group">
        <BrandLogoIcon size="md" full />
      </router-link>

      <div class="flex items-center gap-3">
        <button
          v-if="geo?.country"
          type="button"
          class="hidden sm:flex items-center gap-1.5 text-xs text-slate-400 glass px-3 py-1.5 rounded-full"
          :title="t('location')"
        >
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
          {{ geo.city }}, {{ geo.country }}
        </button>
        <div
          v-if="localeOptions.length > 1"
          class="flex glass rounded-lg p-0.5 text-xs sm:text-sm"
        >
          <button
            v-for="opt in localeOptions"
            :key="opt.code"
            type="button"
            class="px-2.5 sm:px-3 py-1 rounded-md transition-colors shrink-0"
            :class="locale === opt.code ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900'"
            :title="opt.code === 'en' ? 'English' : geo?.country"
            @click="setLocale(opt.code)"
          >
            {{ opt.label }}
          </button>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref, onMounted, inject } from 'vue';
import api from '../services/api';
import { initLocaleFromGeo } from '../i18n';
import BrandLogoIcon from './BrandLogoIcon.vue';

const { locale, t, setLocale, localeOptions } = inject('i18n');
const geo = ref(null);

onMounted(async () => {
  geo.value = await initLocaleFromGeo(api);
});
</script>
