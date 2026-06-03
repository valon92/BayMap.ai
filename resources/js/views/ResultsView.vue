<template>
  <section class="px-4 sm:px-6 lg:px-8 pb-16">
    <div class="max-w-7xl mx-auto">
      <div class="mb-6">
        <router-link to="/" class="text-sm text-slate-400 hover:text-sky-400 transition-colors">
          ← BuyMap.ai
        </router-link>
        <h1 class="text-2xl sm:text-3xl font-bold mt-4">
          {{ t('results_for') }}
          <span class="text-sky-400">"{{ displayQuery }}"</span>
        </h1>

        <form
          class="mt-4 flex items-center gap-2 max-w-3xl"
          @submit.prevent="submitEditedQuery"
        >
          <input
            v-model="editableQuery"
            type="text"
            class="w-full px-4 py-2.5 rounded-xl bg-slate-900/40 border border-white/10 text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
            :placeholder="t('placeholder')"
            :aria-label="t('placeholder')"
          />
          <button
            type="submit"
            class="shrink-0 px-4 py-2.5 rounded-xl font-semibold text-sm text-white bg-gradient-to-r from-sky-600/90 to-violet-600/90 hover:from-sky-500 hover:to-violet-500 border border-white/10 transition-all active:scale-[0.98]"
            :disabled="!editableQuery.trim() || editableQuery.trim() === String(route.query.q || '').trim()"
          >
            {{ t('search') }}
          </button>
        </form>

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

      <div class="results-insights mb-4">
        <InsightScrollSection
          v-if="data?.location_context?.summary"
          :title="t('search_near_landmark')"
          tone="emerald"
        >
          <template #icon>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
            </svg>
          </template>
          <span class="insight-chip insight-chip--card insight-chip--emerald">
            {{ data.location_context.summary }}
          </span>
          <span
            v-for="street in data.location_context.streets"
            :key="street"
            class="insight-chip insight-chip--emerald"
          >
            {{ street }}
          </span>
        </InsightScrollSection>

        <InsightScrollSection
          v-else-if="productDescription"
          :title="t('ai_product_description')"
          tone="violet"
        >
          <template #icon>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
            </svg>
          </template>
          <span class="insight-chip insight-chip--card insight-chip--violet">
            {{ productDescription }}
          </span>
        </InsightScrollSection>

        <InsightScrollSection
          v-if="locationBanner"
          :title="locationBannerTitle"
          tone="sky"
        >
          <template #icon>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064" />
            </svg>
          </template>
          <span class="insight-chip insight-chip--card">
            {{ locationBanner.text }}
          </span>
        </InsightScrollSection>

        <div v-if="!loading">
          <SearchScopeChips
            :model-value="activeScope"
            @update:model-value="onScopeChange"
          />
          <p v-if="scopeSummary" class="text-[11px] text-slate-500 mt-2 px-1">{{ scopeSummary }}</p>
        </div>

        <InsightScrollSection
          v-if="parsedChipItems.length"
          :title="t('parsed_intent')"
          tone="slate"
        >
          <template #icon>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
          </template>
          <span
            v-for="(chip, idx) in parsedChipItems"
            :key="idx"
            class="insight-chip"
            :class="chip.accent ? 'insight-chip--accent' : ''"
          >
            {{ chip.label }}
          </span>
        </InsightScrollSection>

        <InsightScrollSection
          v-if="platformCapabilities.length"
          :title="t('platform_engine')"
          tone="violet"
        >
          <template #icon>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
          </template>
          <span
            v-for="cap in platformCapabilities"
            :key="cap"
            class="insight-chip insight-chip--violet"
          >
            {{ t(`platform_caps.${cap}`) }}
          </span>
        </InsightScrollSection>

        <InsightScrollSection
          v-if="showKosovoMarketplaces"
          :title="t('kosovo_marketplaces')"
          tone="emerald"
        >
          <template #icon>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
            </svg>
          </template>
          <span
            v-for="label in kosovoMarketplaceLabels"
            :key="label"
            class="insight-chip insight-chip--emerald"
          >
            {{ label }}
          </span>
        </InsightScrollSection>

        <InsightScrollSection
          v-if="showDutchCarMarketplaces"
          :title="t('dutch_car_marketplaces')"
          tone="orange"
        >
          <template #icon>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
            </svg>
          </template>
          <span
            v-for="label in dutchMarketplaceLabels"
            :key="label"
            class="insight-chip insight-chip--orange"
          >
            {{ label }}
          </span>
        </InsightScrollSection>

        <InsightScrollSection
          v-if="showSwissCarMarketplaces"
          :title="t('swiss_car_marketplaces')"
          tone="sky"
        >
          <template #icon>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
            </svg>
          </template>
          <span
            v-for="label in swissMarketplaceLabels"
            :key="label"
            class="insight-chip insight-chip--sky"
          >
            {{ label }}
          </span>
        </InsightScrollSection>
      </div>

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
import InsightScrollSection from '../components/InsightScrollSection.vue';
import ProductCard from '../components/ProductCard.vue';
import DynamicFilters from '../components/DynamicFilters.vue';
import ResultsSkeleton from '../components/ResultsSkeleton.vue';
import SearchScopeChips from '../components/SearchScopeChips.vue';

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

const editableQuery = ref(String(route.query.q || ''));

watch(
  () => route.query.q,
  (q) => {
    editableQuery.value = String(q || '');
  }
);

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
const locationBannerTitle = computed(() => {
  if (!locationBanner.value) return '';
  return locationBanner.value.mode === 'query'
    ? t('location_from_query_title')
    : t('location_from_ip_title');
});

const productDescription = computed(
  () => data.value?.vision?.description || data.value?.parsed?.description || ''
);

const parsedChipItems = computed(() => {
  if (!data.value?.parsed || !showParsedTags.value) return [];

  const items = [
    { label: categoryLabel(data.value.parsed.category), accent: true },
  ];

  for (const [key, val] of Object.entries(parsedTags.value)) {
    items.push({
      label: `${fieldLabel(key)}: ${formatTagValue(val)}`,
      accent: false,
    });
  }

  if (showVisitorGeo.value) {
    items.push({
      label: `${data.value.geo.city}, ${data.value.geo.country}`,
      accent: false,
    });
  }

  return items;
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

function submitEditedQuery() {
  const next = editableQuery.value.trim();
  if (!next) return;
  const current = String(route.query.q || '').trim();
  if (next === current) return;

  router.push({
    path: '/search',
    query: {
      ...route.query,
      q: next,
    },
  });
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
