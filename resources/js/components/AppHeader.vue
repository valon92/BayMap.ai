<template>
  <header class="relative z-20 px-4 sm:px-6 lg:px-8 py-5">
    <div class="max-w-6xl mx-auto flex items-center justify-between">
      <router-link to="/" class="flex items-center gap-3 group">
        <BrandLogoIcon
          size="md"
          class="transition-transform duration-300 group-hover:scale-105 group-hover:shadow-sky-500/40"
        />
        <span class="text-xl font-semibold tracking-tight">
          Powerbook<span class="text-sky-400">.ai</span>
        </span>
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
        <div class="flex glass rounded-lg p-0.5 text-sm">
          <button
            type="button"
            class="px-3 py-1 rounded-md transition-colors"
            :class="locale === 'en' ? 'bg-white/10 text-white' : 'text-slate-400 hover:text-white'"
            @click="setLocale('en')"
          >
            EN
          </button>
          <button
            type="button"
            class="px-3 py-1 rounded-md transition-colors"
            :class="locale === 'sq' ? 'bg-white/10 text-white' : 'text-slate-400 hover:text-white'"
            @click="setLocale('sq')"
          >
            SQ
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

const { locale, t, setLocale } = inject('i18n');
const geo = ref(null);

onMounted(async () => {
  geo.value = await initLocaleFromGeo(api);
});
</script>
