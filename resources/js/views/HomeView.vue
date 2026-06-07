<template>
  <section class="px-4 pb-20 overflow-x-clip max-w-full min-w-0">
    <div class="max-w-4xl mx-auto w-full flex flex-col items-center text-center pt-6 sm:pt-14 pb-10">
      <div class="hero-brand-wrap">
        <BrandLogoIcon size="xl" full />
      </div>
      <h1 class="sr-only">BuyMap.ai</h1>

      <div class="hero-badge-wrap">
        <router-link
          to="/how-it-works"
          class="hero-badge hero-badge--link"
          :aria-label="t('how.badge_aria')"
        >
          <span class="relative flex h-2 w-2">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-500 opacity-50" />
            <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-600" />
          </span>
          {{ t('hero_ai_badge') }}
          <svg class="w-3.5 h-3.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </router-link>
      </div>

      <p class="tagline-hero mb-2 max-w-xl mx-auto w-full px-1 sm:px-0">
        {{ tagline }}
      </p>
      <p
        v-if="taglineAlt"
        class="text-slate-500 text-xs sm:text-sm mb-8 max-w-md mx-auto"
      >
        {{ taglineAlt }}
      </p>
      <p v-else class="mb-8" />

      <SearchInput v-model="query" :loading="loading" @search="goSearch" />
    </div>

    <ExamplePrompts @select="onExample" />
    <TrendingSearches @select="onExample" />
  </section>
</template>

<script setup>
import { ref, inject } from 'vue';
import { useRouter } from 'vue-router';
import api from '../services/api';
import BrandLogoIcon from '../components/BrandLogoIcon.vue';
import SearchInput from '../components/SearchInput.vue';
import ExamplePrompts from '../components/ExamplePrompts.vue';
import TrendingSearches from '../components/TrendingSearches.vue';

const router = useRouter();
const { tagline, taglineAlt, locale, t } = inject('i18n');
const query = ref('');
const loading = ref(false);

function goSearch(payload) {
  const text = typeof payload === 'string' ? payload : (payload?.query || '');
  const image = typeof payload === 'object' ? payload?.imageBase64 : null;

  api.saveSearchImage(image);
  loading.value = true;

  const scope = typeof payload === 'object' ? (payload?.locationScope || api.getLocationScope()) : api.getLocationScope();

  router.push({
    name: 'search',
    query: {
      q: text || (image ? 'visual product search' : ''),
      locale: locale.value,
      has_image: image ? '1' : '0',
      scope,
    },
  });
}

function onExample(text) {
  query.value = text;
  api.clearSearchImage();
  goSearch({ query: text, imageBase64: null });
}
</script>
