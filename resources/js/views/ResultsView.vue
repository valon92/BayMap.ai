<template>
  <section class="px-4 sm:px-6 lg:px-8 pb-16 overflow-x-clip max-w-full min-w-0">
    <div class="max-w-7xl mx-auto">
      <div class="mb-6">
        <router-link to="/" class="text-sm text-slate-500 hover:text-blue-600 transition-colors font-medium">
          ← BuyMap.ai
        </router-link>
        <h1 class="text-2xl sm:text-3xl font-bold mt-4 text-slate-900">
          {{ t('results_for') }}
          <span class="text-blue-600">"{{ displayQuery }}"</span>
        </h1>

        <form
          class="mt-4 flex items-center gap-2 max-w-3xl"
          @submit.prevent="submitEditedQuery"
        >
          <input
            v-model="editableQuery"
            type="text"
            class="w-full px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-900 placeholder:text-slate-400 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400"
            :placeholder="t('placeholder')"
            :aria-label="t('placeholder')"
          />
          <button
            type="submit"
            class="shrink-0 px-4 py-2.5 rounded-xl font-semibold text-sm text-white bg-blue-600 hover:bg-blue-700 shadow-[0_4px_14px_-4px_rgba(37,99,235,0.45)] transition-all active:scale-[0.98]"
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
        <img :src="uploadedPreview" alt="" class="h-16 w-16 object-contain rounded-lg bg-slate-50 border border-slate-200" />
        <p class="text-xs text-slate-400">{{ t('searched_by_photo') }}</p>
      </div>

      <div v-if="!loading" class="results-insights-top mb-4">
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

        <p
          v-if="locationBanner"
          class="text-xs text-slate-500 mb-3 px-1"
        >
          {{ locationBanner.text }}
        </p>

        <div v-if="!loading">
          <SearchScopeChips
            :model-value="activeScope"
            @update:model-value="onScopeChange"
          />
          <p v-if="scopeSummary" class="text-[11px] text-slate-500 mt-2 px-1">{{ scopeSummary }}</p>
        </div>

        <InsightScrollSection
          v-if="productDescription"
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
          v-if="valonWorkerChips.length"
          :title="t('valon_workers')"
          tone="violet"
        >
          <template #icon>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </template>
          <span
            v-for="chip in valonWorkerChips"
            :key="chip.id"
            class="insight-chip"
            :class="chip.blocked ? 'insight-chip--muted' : 'insight-chip--violet'"
            :title="chip.role"
          >
            {{ chip.label }}
          </span>
        </InsightScrollSection>
      </div>

      <SearchLoadingExperience v-if="loading" :query="displayQuery" />

      <div
        v-else
        class="results-main flex flex-col lg:grid lg:grid-cols-[minmax(200px,220px)_1fr] xl:grid-cols-[minmax(200px,240px)_1fr] gap-4 xl:gap-5"
      >
        <DynamicFilters
          v-if="data?.filters?.length"
          :key="String(route.query.q || '')"
          :filters="data.filters"
          v-model="activeFilters"
          class="results-filters order-1 lg:order-none mb-0"
          @change="refineSearch"
        />

        <div class="results-products order-2 lg:order-none min-w-0">
          <InsightScrollSection
            v-if="showBookMarketplaces"
            :title="t('book_marketplaces')"
            tone="sky"
            class="mb-4"
          >
            <template #icon>
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
              </svg>
            </template>
            <span
              v-for="label in bookMarketplaceLabels"
              :key="label"
              class="insight-chip insight-chip--sky"
            >
              {{ label }}
            </span>
          </InsightScrollSection>

          <InsightScrollSection
            v-for="section in localMarketplaceSections"
            :key="section.code"
            :title="section.title"
            tone="orange"
            class="mb-4"
          >
            <template #icon>
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
              </svg>
            </template>
            <span
              v-for="label in section.labels"
              :key="`${section.code}-${label}`"
              class="insight-chip insight-chip--orange"
            >
              {{ label }}
            </span>
          </InsightScrollSection>

          <p v-if="!results.length" class="text-center text-slate-400 py-16 glass rounded-2xl">
            {{ t('no_results') }}
          </p>
          <div v-else>
            <TravelRouteSummary v-if="isTravelResults" :parsed="data?.parsed" class="mb-4" />

            <WebServicesSummary v-if="isWebServicesResults" :parsed="data?.parsed" class="mb-4" />

            <div
              v-if="isWebServicesResults"
              class="web-services-results-list"
            >
              <section
                v-for="group in webServiceGroups"
                :key="group.type"
                class="web-services-results-section"
              >
                <header class="web-services-results-section__header">
                  <span class="web-services-results-section__icon" aria-hidden="true">{{ group.icon }}</span>
                  <div>
                    <h3 class="web-services-results-section__title">{{ group.label }}</h3>
                    <p v-if="group.subtitle" class="web-services-results-section__subtitle">{{ group.subtitle }}</p>
                  </div>
                </header>
                <div class="web-services-results-section__cards">
                  <WebServicesCard
                    v-for="product in group.items"
                    :key="product.id + (product.source_key || '')"
                    :product="product"
                  />
                </div>
              </section>
            </div>

            <div
              v-else-if="isTravelResults"
              class="travel-results-list space-y-3"
            >
              <TravelCard
                v-for="product in results"
                :key="product.id + (product.source_key || '')"
                :product="product"
              />
            </div>

            <div
              v-else
              class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2.5 sm:gap-3"
            >
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
                class="px-6 py-3 rounded-xl font-medium text-sm bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 hover:border-blue-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
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

      <div v-if="hasDetailInsights" class="results-insights mt-8">
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
import TravelCard from '../components/TravelCard.vue';
import TravelRouteSummary from '../components/TravelRouteSummary.vue';
import WebServicesCard from '../components/WebServicesCard.vue';
import WebServicesSummary from '../components/WebServicesSummary.vue';
import DynamicFilters from '../components/DynamicFilters.vue';
import SearchLoadingExperience from '../components/SearchLoadingExperience.vue';
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
const perPage = 48;
let debounceTimer = null;
let lastQueryKey = '';

const platformCapabilities = computed(() => data.value?.platform?.positioning ?? []);

function normalizePlatformToken(value) {
  return String(value || '').toLowerCase().replace(/[^a-z0-9]/g, '');
}

function workerMatchesResults(worker, items) {
  const needles = [
    normalizePlatformToken(worker.platform),
    normalizePlatformToken(worker.platform_label),
  ].filter((token) => token.length >= 3);

  if (!needles.length || !items.length) {
    return (worker.results ?? 0) > 0;
  }

  return items.some((product) => {
    const haystacks = [
      normalizePlatformToken(product.source_key),
      normalizePlatformToken(product.source),
      normalizePlatformToken(product.store),
    ];

    return needles.some((needle) => haystacks.some((hay) => hay.includes(needle) || needle.includes(hay)));
  });
}

function workerPlatformKey(worker) {
  return normalizePlatformToken(worker.platform_label || worker.platform);
}

function workerPriority(worker, items) {
  if (worker.status === 'blocked') return 0;
  if ((worker.results ?? 0) > 0) return 3;
  if (workerMatchesResults(worker, items)) return 2;
  return 1;
}

const valonWorkerChips = computed(() => {
  const workers = data.value?.meta?.valon?.workers ?? [];
  const pool = results.value;
  const poolSize = data.value?.meta?.pool_size ?? pool.length;

  const ranked = workers
    .filter((w) => {
      if (poolSize > 0) {
        if (w.status === 'blocked') {
          return !workers.some(
            (other) =>
              other !== w
              && workerPlatformKey(other) === workerPlatformKey(w)
              && ((other.results ?? 0) > 0 || workerMatchesResults(other, pool)),
          );
        }
        return (w.results ?? 0) > 0 || workerMatchesResults(w, pool);
      }

      if (w.status === 'blocked') {
        return true;
      }
      if ((w.results ?? 0) > 0) {
        return true;
      }

      return !String(w.id || '').includes('demo');
    })
    .sort((a, b) => workerPriority(b, pool) - workerPriority(a, pool));

  const seen = new Set();
  const deduped = [];
  for (const w of ranked) {
    const key = workerPlatformKey(w);
    if (key && seen.has(key)) {
      continue;
    }
    if (key) {
      seen.add(key);
    }
    deduped.push(w);
  }

  return deduped.map((w) => {
    const count = w.status === 'blocked'
      ? t('worker_blocked')
      : w.results != null
        ? ` · ${w.results}`
        : '';

    return {
      id: w.id,
      role: w.role,
      label: `${w.platform_label || w.platform}${count}`,
      blocked: w.status === 'blocked',
    };
  });
});

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

const isTravelResults = computed(() => {
  const cat = String(data.value?.parsed?.category || '').toLowerCase();
  return cat === 'travel' || cat === 'travel & tourism';
});

const WEB_SERVICE_TYPES = ['domain', 'hosting', 'email', 'ssl', 'website'];
const WEB_SERVICE_ORDER = ['domain', 'hosting', 'email', 'ssl', 'website'];
const WEB_SERVICE_ICONS = { domain: '🌐', hosting: '🖥️', email: '✉️', ssl: '🔒', website: '🔗' };

const isWebServicesResults = computed(() => {
  const p = data.value?.parsed;
  if (!p) return false;

  const cat = String(p.category || '').toLowerCase();
  const type = String(p.web_service_type || p.product_type || '').toLowerCase();
  const types = Array.isArray(p.web_service_types) ? p.web_service_types : [];

  if (cat === 'ai_software' && (WEB_SERVICE_TYPES.includes(type) || type === 'combo' || types.length > 0)) {
    return true;
  }

  const first = results.value[0];
  return Boolean(first?.price_on_request && WEB_SERVICE_TYPES.includes(String(first?.web_service_type || first?.product_type || '').toLowerCase()));
});

const webServiceGroups = computed(() => {
  if (!isWebServicesResults.value || !results.value.length) return [];

  const buckets = {};
  for (const product of results.value) {
    const type = String(product.web_service_type || product.product_type || 'domain').toLowerCase();
    if (!buckets[type]) buckets[type] = [];
    buckets[type].push(product);
  }

  const domain = String(data.value?.parsed?.domain_query || data.value?.parsed?.domain_name || '').trim();

  return WEB_SERVICE_ORDER
    .filter((type) => buckets[type]?.length)
    .map((type) => ({
      type,
      icon: WEB_SERVICE_ICONS[type] || '🌐',
      label: t(`web_services_mode_${type}`, type),
      subtitle: type === 'domain' && domain ? domain : null,
      items: [...buckets[type]].sort((a, b) => (
        (a.provider_rank ?? 999) - (b.provider_rank ?? 999)
      )),
    }));
});

const showBookMarketplaces = computed(() => {
  const p = data.value?.parsed;
  const cat = String(p?.category || '');
  const type = String(p?.product_type || '').toLowerCase();
  return cat === 'online_education'
    || ['book', 'libër', 'liber', 'librin', 'ebook', 'audiobook'].includes(type);
});

const bookMarketplaceLabels = computed(() => {
  if (!showBookMarketplaces.value) return [];
  return data.value?.meta?.marketplace_labels ?? [];
});

const COUNTRY_DISPLAY = {
  CH: 'Switzerland',
  DE: 'Germany',
  NL: 'Netherlands',
  XK: 'Kosovo',
  GB: 'United Kingdom',
  US: 'United States',
  AL: 'Albania',
  FR: 'France',
  IT: 'Italy',
  AT: 'Austria',
};

function countryDisplayName(code, parsed) {
  const normalized = String(code || '').toUpperCase();
  const multi = parsed?.search_countries;
  if (Array.isArray(multi)) {
    const match = multi.find((c) => String(c?.search_country_code || '').toUpperCase() === normalized);
    if (match?.search_country) return match.search_country;
  }
  if (parsed?.search_country && String(parsed?.search_country_code || '').toUpperCase() === normalized) {
    return parsed.search_country;
  }
  return COUNTRY_DISPLAY[normalized] || normalized;
}

const localMarketplaceSections = computed(() => {
  const p = data.value?.parsed;
  if (!p?.search_target || showBookMarketplaces.value) return [];

  const byCountry = data.value?.meta?.marketplace_labels_by_country ?? {};
  const entries = Object.entries(byCountry).filter(([, labels]) => Array.isArray(labels) && labels.length);

  if (entries.length) {
    return entries.map(([code, labels]) => ({
      code,
      title: t('local_marketplaces_searched', { country: countryDisplayName(code, p) }),
      labels,
    }));
  }

  const labels = data.value?.meta?.marketplace_labels ?? [];
  const code = String(p.search_country_code || '').toUpperCase();
  if (!labels.length || !code || code === 'XK') return [];

  const pool = results.value;
  if (pool.length) {
    const fromResults = [...new Set(pool.map((item) => item.source).filter(Boolean))];
    if (fromResults.length) {
      return [{
        code,
        title: t('local_marketplaces_searched', { country: countryDisplayName(code, p) }),
        labels: fromResults,
      }];
    }
  }

  return [{
    code,
    title: t('local_marketplaces_searched', { country: countryDisplayName(code, p) }),
    labels,
  }];
});

const locationBanner = computed(() => {
  const loc = data.value?.meta?.location;
  if (!loc?.label) return null;

  if (loc.mode === 'query') {
    if (loc.travel_route) {
      return {
        mode: 'query',
        text: t('travel_route_scope', { route: loc.label }),
      };
    }
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
      label: `${fieldLabel(key)}: ${formatTagValue(val, key)}`,
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

const hasDetailInsights = computed(() => Boolean(
  data.value?.location_context?.summary
  || showKosovoMarketplaces.value
));

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

function formatTagValue(val, key) {
  if (Array.isArray(val)) {
    if (key === 'search_countries') {
      return val
        .map((c) => (typeof c === 'object' && c ? (c.search_country || c.search_country_code) : c))
        .filter(Boolean)
        .join(', ');
    }
    return val.join(', ');
  }
  if (typeof val === 'boolean') return val ? '✓' : '—';
  if (key === 'gender') {
    const gk = `gender_values.${val}`;
    const gl = t(gk);
    if (gl !== gk) return gl;
  }
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

function filtersFromResponse(response) {
  const initial = {};
  for (const filter of response?.filters || []) {
    if (filter.value != null && filter.value !== '') {
      initial[filter.key] = filter.value;
    }
  }
  return initial;
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
    activeFilters.value = filtersFromResponse(response);
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
  data.value = null;
  visibleResults.value = [];
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
