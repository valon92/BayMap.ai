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
          <template v-if="results.length">
            {{ t('showing_results', { shown: results.length, total: formatTotal(data.meta.total) }) }}
          </template>
          <template v-else>
            {{ formatTotal(data.meta.total) }} {{ t('matches') }}
          </template>
        </p>
      </div>

      <div
        v-if="uploadedPreview"
        class="glass rounded-xl p-3 mb-4 inline-flex items-center gap-3"
      >
        <img :src="uploadedPreview" alt="" class="h-16 w-16 object-contain rounded-lg bg-white/10" />
        <p class="text-xs text-slate-400">{{ t('searched_by_photo') }}</p>
      </div>

      <div
        v-if="data?.location_context?.summary"
        class="glass rounded-xl p-4 mb-6 border border-emerald-500/20"
      >
        <p class="text-xs uppercase tracking-wider text-emerald-300 mb-2">{{ t('search_near_landmark') }}</p>
        <p class="text-sm text-slate-300 leading-relaxed">{{ data.location_context.summary }}</p>
        <div v-if="data.location_context.streets?.length" class="flex flex-wrap gap-1.5 mt-3">
          <span
            v-for="street in data.location_context.streets"
            :key="street"
            class="px-2 py-0.5 rounded-md text-[11px] bg-white/5 text-slate-400 border border-white/10"
          >
            {{ street }}
          </span>
        </div>
      </div>

      <div
        v-else-if="data?.vision?.description || data?.parsed?.description"
        class="glass rounded-xl p-4 mb-6 border border-violet-500/20"
      >
        <p class="text-xs uppercase tracking-wider text-violet-300 mb-2">{{ t('ai_product_description') }}</p>
        <p class="text-sm text-slate-300 leading-relaxed">
          {{ data.vision?.description || data.parsed.description }}
        </p>
      </div>

      <div
        v-if="locationBanner"
        class="glass rounded-xl p-4 mb-6 border"
        :class="locationBanner.mode === 'query' ? 'border-sky-500/25' : 'border-white/10'"
      >
        <p class="text-xs uppercase tracking-wider mb-1.5" :class="locationBanner.mode === 'query' ? 'text-sky-300' : 'text-slate-400'">
          {{ locationBanner.mode === 'query' ? t('location_from_query_title') : t('location_from_ip_title') }}
        </p>
        <p class="text-sm text-slate-300">{{ locationBanner.text }}</p>
      </div>

      <div v-if="!loading" class="mb-4 max-w-xl -mx-1 sm:mx-0 overflow-visible">
        <SearchScopeChips
          :model-value="activeScope"
          @update:model-value="onScopeChange"
        />
        <p v-if="scopeSummary" class="text-[11px] text-slate-500 mt-2">{{ scopeSummary }}</p>
      </div>

      <div
        v-if="data?.parsed && showParsedTags"
        class="glass rounded-xl p-4 mb-6 flex flex-wrap gap-2 items-center"
      >
        <span class="text-xs text-slate-400">{{ t('parsed_intent') }}:</span>
        <span class="px-2 py-1 rounded-lg bg-sky-500/20 text-sky-300 text-xs font-medium">
          {{ categoryLabel(data.parsed.category) }}
        </span>
        <span
          v-for="(val, key) in parsedTags"
          :key="key"
          class="px-2 py-1 rounded-lg bg-white/5 text-slate-300 text-xs"
        >
          {{ fieldLabel(key) }}: {{ formatTagValue(val) }}
        </span>
        <span v-if="showVisitorGeo" class="ml-auto text-xs text-slate-500">
          {{ data.geo.city }}, {{ data.geo.country }}
        </span>
      </div>

      <div
        v-if="platformCapabilities.length"
        class="glass rounded-xl p-4 mb-6 border border-violet-500/20"
      >
        <p class="text-xs uppercase tracking-wider text-violet-300 mb-3">{{ t('platform_engine') }}</p>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="cap in platformCapabilities"
            :key="cap"
            class="px-2.5 py-1 rounded-lg text-xs bg-violet-500/10 text-violet-200 border border-violet-500/25"
          >
            {{ t(`platform_caps.${cap}`) }}
          </span>
        </div>
      </div>

      <div
        v-if="showKosovoMarketplaces"
        class="glass rounded-xl p-4 mb-6 border border-emerald-500/20"
      >
        <p class="text-xs uppercase tracking-wider text-emerald-300 mb-3">{{ t('kosovo_marketplaces') }}</p>
        <div class="flex flex-wrap gap-2 max-h-32 overflow-y-auto">
          <span
            v-for="label in kosovoMarketplaceLabels"
            :key="label"
            class="px-2.5 py-1 rounded-lg text-xs bg-emerald-500/10 text-emerald-200 border border-emerald-500/25"
          >
            {{ label }}
          </span>
        </div>
      </div>

      <div
        v-if="showDutchCarMarketplaces"
        class="glass rounded-xl p-4 mb-6 border border-orange-500/20"
      >
        <p class="text-xs uppercase tracking-wider text-orange-300 mb-3">{{ t('dutch_car_marketplaces') }}</p>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="label in dutchMarketplaceLabels"
            :key="label"
            class="px-2.5 py-1 rounded-lg text-xs bg-orange-500/10 text-orange-200 border border-orange-500/25"
          >
            {{ label }}
          </span>
        </div>
      </div>

      <div
        v-if="showSwissCarMarketplaces"
        class="glass rounded-xl p-4 mb-6 border border-sky-500/20"
      >
        <p class="text-xs uppercase tracking-wider text-sky-300 mb-3">{{ t('swiss_car_marketplaces') }}</p>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="label in swissMarketplaceLabels"
            :key="label"
            class="px-2.5 py-1 rounded-lg text-xs bg-sky-500/10 text-sky-200 border border-sky-500/25"
          >
            {{ label }}
          </span>
        </div>
      </div>

      <SearchPipeline
        v-if="data?.pipeline?.length || data?.meta?.source_report?.length"
        :pipeline="data.pipeline"
        :report="data.meta?.source_report || []"
      />

      <div class="lg:grid lg:grid-cols-[minmax(200px,220px)_1fr] xl:grid-cols-[minmax(200px,240px)_1fr] gap-4 xl:gap-5">
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
          <div v-else>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2.5 sm:gap-3">
              <ProductCard
                v-for="product in results"
                :key="product.id + (product.source_key || '')"
                :product="product"
                class="group"
              />
            </div>

            <div v-if="hasMore" class="mt-8 flex flex-col items-center gap-2">
              <button
                type="button"
                class="px-6 py-3 rounded-xl font-medium text-sm bg-sky-500/20 text-sky-300 border border-sky-500/40 hover:bg-sky-500/30 hover:border-sky-400/60 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="loadingMore"
                @click="loadMore"
              >
                {{ loadingMore ? t('loading_more') : t('load_more') }}
              </button>
              <p class="text-xs text-slate-500">
                {{ t('showing_results', { shown: results.length, total: formatTotal(data?.meta?.total) }) }}
              </p>
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
import SearchScopeChips from '../components/SearchScopeChips.vue';
import SearchPipeline from '../components/SearchPipeline.vue';

const route = useRoute();
const router = useRouter();
const { t, locale, setLocale } = inject('i18n');

const data = ref(null);
const loading = ref(true);
const loadingMore = ref(false);
const currentPage = ref(1);
const visibleResults = ref([]);
const activeFilters = ref({});
const perPage = 20;
let debounceTimer = null;
let lastQueryKey = '';

const platformCapabilities = computed(() => data.value?.platform?.positioning ?? []);

const displayQuery = computed(() => {
  if (route.query.has_image === '1' && data.value?.vision?.search_query) {
    return data.value.vision.search_query;
  }
  return route.query.q || '';
});
const results = computed(() => visibleResults.value);
const hasMore = computed(() => Boolean(data.value?.meta?.has_more));
const showKosovoMarketplaces = computed(() => {
  const code = String(data.value?.expanded?.search_country_code || data.value?.geo?.country_code || '').toUpperCase();
  const searchingAbroad = Boolean(
    data.value?.parsed?.search_target
    && String(data.value?.parsed?.search_country_code || '').toUpperCase() !== 'XK'
  );

  return code === 'XK' && ! searchingAbroad && (data.value?.meta?.marketplace_labels?.length > 0);
});

const kosovoMarketplaceLabels = computed(() => {
  if (!showKosovoMarketplaces.value) return [];
  return data.value?.meta?.marketplace_labels ?? [];
});

const showDutchCarMarketplaces = computed(() => {
  const p = data.value?.parsed;
  return Boolean(
    p?.search_target
    && String(p?.search_country_code || '').toUpperCase() === 'NL'
    && (p?.category === 'automotive' || p?.category === 'car')
  );
});

const dutchMarketplaceLabels = computed(() => {
  if (!showDutchCarMarketplaces.value) return [];
  return data.value?.meta?.marketplace_labels ?? [];
});

const showSwissCarMarketplaces = computed(() => {
  const p = data.value?.parsed;
  return Boolean(
    p?.search_target
    && String(p?.search_country_code || '').toUpperCase() === 'CH'
    && (p?.category === 'automotive' || p?.category === 'car')
  );
});

const swissMarketplaceLabels = computed(() => {
  if (!showSwissCarMarketplaces.value) return [];
  return data.value?.meta?.marketplace_labels ?? [];
});

const locationBanner = computed(() => {
  const loc = data.value?.meta?.location;
  if (!loc?.label) return null;

  if (loc.mode === 'query') {
    return {
      mode: 'query',
      text: t('location_from_query', { country: loc.label }),
    };
  }

  return {
    mode: 'ip',
    text: t('location_from_ip', {
      city: loc.visitor_city || t('scope_city'),
      country: loc.visitor_country || '',
    }),
  };
});

const showVisitorGeo = computed(() => {
  const loc = data.value?.meta?.location;
  return !loc?.target_country && data.value?.geo?.city;
});

const scopeSummary = computed(() => {
  const loc = data.value?.meta?.location;
  if (loc?.mode === 'query' && loc?.label) {
    return `${t('search_area')}: ${loc.label}`;
  }
  const tiers = data.value?.meta?.location_tiers;
  if (!tiers?.length) return '';
  const labels = tiers.map((t) => t.label).filter(Boolean);
  return labels.length ? `${t('search_area')}: ${labels.join(' → ')}` : '';
});
const uploadedPreview = ref(null);

const activeScope = ref(api.getLocationScope());

const showParsedTags = computed(() => {
  const cat = data.value?.parsed?.category;
  return cat && cat !== 'real_estate';
});

const parsedTags = computed(() => {
  if (!data.value?.parsed) return {};
  const skip = [
    'raw_query', 'category', 'keywords', 'country', 'language_hint', 'description', 'vision',
    'search_query', 'nearby_streets', 'neighborhoods', 'landmark', 'landmark_label', 'area_summary',
    'near_landmark', 'city', 'location_source', 'search_target',
  ];
  return Object.fromEntries(
    Object.entries(data.value.parsed).filter(([k, v]) => !skip.includes(k) && v != null && v !== '')
  );
});

function categoryLabel(cat) {
  const key = `categories.${cat}`;
  const label = t(key);
  return label !== key ? label : cat;
}

function fieldLabel(key) {
  const k = `parsed_fields.${key}`;
  const label = t(k);
  return label !== k ? label : key;
}

function formatTagValue(val) {
  if (Array.isArray(val)) return val.join(', ');
  if (typeof val === 'boolean') return val ? '✓' : '—';
  return String(val);
}

function formatTotal(total) {
  const n = Number(total);
  if (!Number.isFinite(n) || n <= 0) return '0';
  return n.toLocaleString(locale.value === 'sq' ? 'sq-AL' : 'en-US');
}

function onScopeChange(scope) {
  activeScope.value = scope;
  api.setLocationScope(scope);
  router.replace({ query: { ...route.query, scope } });
}

async function fetchPage(page, append = false) {
  const q = String(route.query.q || '');
  const imageBase64 = api.loadSearchImage();
  const hasImage = route.query.has_image === '1' && imageBase64;

  const response = await api.search(
    q || 'find this product',
    mapFilters(activeFilters.value),
    locale.value,
    hasImage ? imageBase64 : null,
    activeScope.value,
    page,
    perPage
  );

  if (append) {
    visibleResults.value = [...visibleResults.value, ...(response.results || [])];
    data.value = { ...data.value, ...response, results: visibleResults.value };
  } else {
    data.value = response;
    visibleResults.value = response.results || [];
  }

  currentPage.value = page;
  if (!append) {
    api.clearSearchImage();
  }

  return response;
}

async function refreshForLocale() {
  if (loading.value) return;
  loading.value = true;
  currentPage.value = 1;
  try {
    await fetchPage(1, false);
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function runSearch() {
  const q = String(route.query.q || '');
  const imageBase64 = api.loadSearchImage();
  const hasImage = route.query.has_image === '1' && imageBase64;
  const queryKey = `${q}|${hasImage ? 'img' : ''}`;

  if (!hasImage && q.length < 3) {
    router.replace({ name: 'home' });
    return;
  }

  if (queryKey !== lastQueryKey) {
    activeFilters.value = {};
    lastQueryKey = queryKey;
  }

  if (route.query.scope) {
    activeScope.value = String(route.query.scope);
    api.setLocationScope(activeScope.value);
  }

  uploadedPreview.value = hasImage ? `data:image/jpeg;base64,${imageBase64}` : null;
  loading.value = true;
  currentPage.value = 1;

  try {
    await fetchPage(1, false);
  } catch (e) {
    console.error(e);
    data.value = { results: [], filters: [], pipeline: [], meta: { total: 0, has_more: false } };
    visibleResults.value = [];
  } finally {
    loading.value = false;
  }
}

async function loadMore() {
  if (!hasMore.value || loadingMore.value || loading.value) return;

  loadingMore.value = true;
  try {
    await fetchPage(currentPage.value + 1, true);
  } catch (e) {
    console.error(e);
  } finally {
    loadingMore.value = false;
  }
}

function mapFilters(filters) {
  const mapped = { ...filters };
  if (mapped.price != null) {
    mapped.price_max = mapped.price;
    delete mapped.price;
  }
  if (mapped.size != null && mapped.size !== '') {
    mapped.size = String(mapped.size);
  }
  if (mapped.country != null) {
    const country = String(mapped.country).toLowerCase();
    if (country.includes('world') || country.includes('universal') || country.includes('bot')) {
      delete mapped.country;
    }
  }
  return mapped;
}

function refineSearch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(runSearch, 400);
}

watch(() => [route.query.q, route.query.scope], runSearch);

watch(locale, (newLocale) => {
  if (route.name !== 'search') return;
  if (String(route.query.locale || '') === newLocale) return;
  router.replace({ query: { ...route.query, locale: newLocale } });
  refreshForLocale();
});

watch(
  () => route.query.locale,
  (routeLocale) => {
    if (!routeLocale || routeLocale === locale.value) return;
    setLocale(String(routeLocale));
    refreshForLocale();
  }
);

onMounted(() => {
  if (route.query.locale) {
    setLocale(String(route.query.locale));
  }
  runSearch();
});
</script>
