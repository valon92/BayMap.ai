<template>
  <section class="px-4 pb-20">
    <div class="max-w-4xl mx-auto text-center pt-6 sm:pt-14 pb-10">
      <div class="flex justify-center mb-5">
        <BrandLogoIcon size="xl" class="shadow-2xl shadow-violet-500/20" />
      </div>
      <h1 class="sr-only">Powerbook.ai</h1>

      <p class="hero-badge mx-auto">
        <span class="relative flex h-2 w-2">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-sky-400 opacity-60" />
          <span class="relative inline-flex rounded-full h-2 w-2 bg-sky-400" />
        </span>
        {{ t('hero_ai_badge') }}
      </p>

      <p class="tagline-hero text-slate-300/90 mb-8 max-w-xl mx-auto w-full px-1 sm:px-0 sm:text-xl font-medium">
        {{ tagline }}
      </p>

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
const { tagline, locale, t } = inject('i18n');
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
