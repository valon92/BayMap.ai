<template>
  <section class="px-4 pb-20">
    <div class="max-w-4xl mx-auto text-center pt-8 sm:pt-16 pb-10">
      <div class="flex justify-center mb-5">
        <BrandLogoIcon size="xl" class="shadow-2xl shadow-violet-500/20" />
      </div>
      <h1 class="sr-only">Powerbook.ai</h1>
      <p class="tagline-hero text-slate-400 mb-10 max-w-xl mx-auto w-full px-1 sm:px-0 sm:text-xl">
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
import BrandLogoIcon from '../components/BrandLogoIcon.vue';
import SearchInput from '../components/SearchInput.vue';
import ExamplePrompts from '../components/ExamplePrompts.vue';
import TrendingSearches from '../components/TrendingSearches.vue';

const router = useRouter();
const { tagline, locale } = inject('i18n');
const query = ref('');
const loading = ref(false);

function goSearch(q) {
  loading.value = true;
  router.push({
    name: 'search',
    query: { q, locale: locale.value },
  });
}

function onExample(text) {
  query.value = text;
  goSearch(text);
}
</script>
