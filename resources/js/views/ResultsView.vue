<template>
  <section class="px-4 sm:px-6 lg:px-8 pb-16">
    <div class="max-w-7xl mx-auto">
      <div class="mb-6">
        <router-link to="/" class="text-sm text-slate-400 hover:text-sky-400 transition-colors">
          ← Powerbook.ai
        </router-link>
        <h1 class="text-2xl sm:text-3xl font-bold mt-4">
          {{ t('results_for') }}
          <span class="text-sky-400">"{{ displayQuery }}"</span>
        </h1>
        <p v-if="data?.meta" class="text-sm text-slate-500 mt-2">
          {{ data.meta.total }} {{ t('matches') }} · {{ data.meta.processing_ms }}ms
        </p>
      </div>

      <div v-if="data?.parsed" class="glass rounded-xl p-4 mb-6 flex flex-wrap gap-2 items-center">
        <span class="text-xs text-slate-400">{{ t('parsed_intent') }}:</span>
        <span class="px-2 py-1 rounded-lg bg-sky-500/20 text-sky-300 text-xs font-medium">
          {{ categoryLabel(data.parsed.category) }}
        </span>
        <span
          v-for="(val, key) in parsedTags"
          :key="key"
          class="px-2 py-1 rounded-lg bg-white/5 text-slate-300 text-xs"
        >
          {{ key }}: {{ val }}
        </span>
        <span v-if="data.geo" class="ml-auto text-xs text-slate-500">
          {{ data.geo.city }}, {{ data.geo.country }}
        </span>
      </div>

      <div class="lg:grid lg:grid-cols-[240px_1fr] gap-6">
        <DynamicFilters
          v-if="data?.filters?.length"
          :filters="data.filters"
          v-model="activeFilters"
          class="mb-6 lg:mb-0"
          @change="refineSearch"
        />

        <div>
          <div v-if="loading" class="mb-4 text-slate-400 text-sm animate-pulse">{{ t('searching') }}</div>
          <ResultsSkeleton v-if="loading" />
          <p v-else-if="!results.length" class="text-center text-slate-400 py-16 glass rounded-2xl">
            {{ t('no_results') }}
          </p>
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            <ProductCard
              v-for="product in results"
              :key="product.id + (product.source_key || '')"
              :product="product"
              class="group"
            />
          </div>

          <div v-if="data?.expanded?.marketplaces" class="mt-8 text-center">
            <p class="text-xs text-slate-500 mb-2">{{ t('sources') }}</p>
            <div class="flex flex-wrap justify-center gap-2">
              <span
                v-for="src in data.expanded.marketplaces"
                :key="src"
                class="glass px-3 py-1 rounded-full text-xs text-slate-400"
              >
                {{ src }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, watch, onMounted, inject } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../services/api';
import ProductCard from '../components/ProductCard.vue';
import DynamicFilters from '../components/DynamicFilters.vue';
import ResultsSkeleton from '../components/ResultsSkeleton.vue';

const route = useRoute();
const router = useRouter();
const { t, locale, setLocale } = inject('i18n');

const data = ref(null);
const loading = ref(true);
const activeFilters = ref({});
let debounceTimer = null;

const displayQuery = computed(() => route.query.q || '');
const results = computed(() => data.value?.results || []);

const parsedTags = computed(() => {
  if (!data.value?.parsed) return {};
  const skip = ['raw_query', 'category', 'keywords', 'country', 'language_hint'];
  return Object.fromEntries(
    Object.entries(data.value.parsed).filter(([k, v]) => !skip.includes(k) && v != null && v !== '')
  );
});

function categoryLabel(cat) {
  return t(`categories.${cat}`) || cat;
}

async function runSearch() {
  const q = route.query.q;
  if (!q || String(q).length < 3) {
    router.replace({ name: 'home' });
    return;
  }

  if (route.query.locale) {
    setLocale(route.query.locale);
  }

  loading.value = true;
  try {
    data.value = await api.search(String(q), mapFilters(activeFilters.value), locale.value);
  } catch (e) {
    console.error(e);
    data.value = { results: [], filters: [] };
  } finally {
    loading.value = false;
  }
}

function mapFilters(filters) {
  const mapped = { ...filters };
  if (mapped.price != null) {
    mapped.price_max = mapped.price;
    delete mapped.price;
  }
  return mapped;
}

function refineSearch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(runSearch, 400);
}

watch(() => route.query.q, runSearch);
onMounted(runSearch);
</script>
