<template>
  <div
    class="market-picker"
    :class="{
      'market-picker--embedded': variant === 'embedded',
      'market-picker--open': expanded,
    }"
  >
    <div class="market-picker-bar">
      <button
        type="button"
        class="market-picker-trigger"
        :disabled="disabled"
        :aria-expanded="expanded"
        @click="toggleExpanded"
      >
        <span class="market-picker-icon" aria-hidden="true">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </span>
        <span class="market-picker-trigger-text">
          <span class="market-picker-label">{{ t('market_picker_title') }}</span>
          <span class="market-picker-value">{{ selectedLabel }}</span>
        </span>
        <svg
          class="market-picker-chevron"
          :class="{ 'market-picker-chevron--open': expanded }"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      <button
        v-if="expanded"
        type="button"
        class="market-picker-hide"
        :disabled="disabled"
        :aria-label="t('market_hide')"
        :title="t('market_hide')"
        @click="collapse"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <div v-show="expanded" class="market-picker-panel">
      <div class="market-picker-quick" role="group" :aria-label="t('market_picker_title')">
        <button
          type="button"
          class="market-pill"
          :class="{ 'market-pill--active': modelValue.mode === 'auto' }"
          :disabled="disabled"
          @click="selectAuto"
        >
          {{ autoLabelShort }}
        </button>
        <button
          type="button"
          class="market-pill"
          :class="{ 'market-pill--active': modelValue.mode === 'global' }"
          :disabled="disabled"
          @click="selectGlobal"
        >
          {{ t('market_global') }}
        </button>
      </div>

      <div v-if="loadingCatalog" class="market-picker-loading">
        <span class="market-picker-spinner" aria-hidden="true" />
        <span>{{ t('market_loading') }}</span>
      </div>

      <template v-else>
        <p class="market-picker-hint">{{ t('market_multi_hint', { max: MAX_SELECTED }) }}</p>
        <p v-if="selectionLimitHit" class="market-limit-msg">
          {{ t('market_max_countries', { max: MAX_SELECTED }) }}
        </p>

        <div v-if="selectedCountryCodes.length" class="market-selected-bar">
          <div class="market-selected-head">
            <span class="market-popular-label">
              {{ t('market_selected_count', { count: selectedCountryCodes.length }) }}
            </span>
            <button type="button" class="market-clear-btn" :disabled="disabled" @click="clearCountries">
              {{ t('market_clear') }}
            </button>
          </div>
          <div class="market-selected-scroll">
            <button
              v-for="code in selectedCountryCodes"
              :key="code"
              type="button"
              class="market-selected-pill"
              :disabled="disabled"
              @click="toggleCountry(code)"
            >
              {{ findCountryName(code) || code }}
              <span aria-hidden="true">×</span>
            </button>
          </div>
          <button type="button" class="market-done-btn" :disabled="disabled" @click="collapse">
            {{ t('market_done') }}
          </button>
        </div>

        <div class="market-search-wrap">
          <label class="sr-only" for="market-country-search">{{ t('market_search_country') }}</label>
          <input
            id="market-country-search"
            v-model="countryQuery"
            type="search"
            class="market-search-input"
            :placeholder="t('market_search_placeholder')"
            :disabled="disabled"
            autocomplete="off"
            @focus="searchFocused = true"
            @blur="onSearchBlur"
          />
          <div v-if="searchFocused && filteredCountries.length" class="market-search-results">
            <button
              v-for="country in filteredCountries"
              :key="country.code"
              type="button"
              class="market-search-result"
              @mousedown.prevent="pickSearchCountry(country)"
            >
              <span>{{ country.name }}</span>
              <span class="market-search-meta">{{ country.continentName }}</span>
            </button>
          </div>
        </div>

        <div v-if="popularCountries.length" class="market-popular">
          <span class="market-popular-label">{{ t('market_popular') }}</span>
          <div class="market-popular-scroll">
            <button
              v-for="country in popularCountries"
              :key="country.code"
              type="button"
              class="market-country-chip"
              :class="{ 'market-country-chip--active': isCountrySelected(country.code) }"
              :disabled="disabled"
              @click="toggleCountry(country.code, country.name, country.continentCode)"
            >
              {{ country.name }}
            </button>
          </div>
        </div>

        <div class="market-regions">
          <span class="market-popular-label">{{ t('market_by_region') }}</span>
          <div class="market-continent-tabs">
            <button
              v-for="continent in continents"
              :key="continent.code"
              type="button"
              class="market-continent-tab"
              :class="{ 'market-continent-tab--active': activeContinent === continent.code }"
              :disabled="disabled"
              @click="setActiveContinent(continent.code)"
            >
              {{ continent.name }}
            </button>
          </div>

          <div v-if="activeContinentMeta" class="market-region-panel">
            <button
              type="button"
              class="market-region-all"
              :class="{ 'market-country-chip--active': isWholeContinentSelected(activeContinentMeta.code) }"
              :disabled="disabled"
              @click="selectContinent(activeContinentMeta.code, activeContinentMeta.name)"
            >
              {{ t('market_whole_continent', { continent: activeContinentMeta.name }) }}
            </button>

            <div class="market-country-grid">
              <button
                v-for="country in activeContinentMeta.countries"
                :key="country.code"
                type="button"
                class="market-country-chip"
                :class="{ 'market-country-chip--active': isCountrySelected(country.code) }"
                :disabled="disabled"
                @click="selectCountry(country.code, country.name, activeContinentMeta.code)"
              >
                {{ country.name }}
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, inject } from 'vue';
import api from '../services/api';

const PICKER_OPEN_KEY = 'powerbook_market_picker_open';
const POPULAR_CODES = ['XK', 'NL', 'DE', 'CH', 'AL', 'IT', 'FR', 'GB', 'US'];
const MAX_SELECTED = 8;

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({ mode: 'auto', countryCode: null, countryCodes: [], continentCode: null }),
  },
  disabled: { type: Boolean, default: false },
  variant: { type: String, default: 'default' },
});

const emit = defineEmits(['update:modelValue']);

const { t, locale } = inject('i18n');

const continents = ref([]);
const visitor = ref(null);
const loadingCatalog = ref(true);
const activeContinent = ref(null);
const countryQuery = ref('');
const searchFocused = ref(false);
const expanded = ref(readExpandedPreference());
const selectionLimitHit = ref(false);

const selectedCountryCodes = computed(() => {
  const sel = props.modelValue;
  if (Array.isArray(sel.countryCodes) && sel.countryCodes.length) {
    return sel.countryCodes.map((c) => String(c).toUpperCase());
  }
  if ((sel.mode === 'country' || sel.mode === 'countries') && sel.countryCode) {
    return [String(sel.countryCode).toUpperCase()];
  }
  return [];
});

const allCountries = computed(() => {
  const list = [];
  for (const continent of continents.value) {
    for (const country of continent.countries || []) {
      list.push({
        code: country.code,
        name: country.name,
        continentCode: continent.code,
        continentName: continent.name,
      });
    }
  }
  return list;
});

const popularCountries = computed(() => {
  const byCode = new Map(allCountries.value.map((c) => [String(c.code).toUpperCase(), c]));
  return POPULAR_CODES.map((code) => byCode.get(code)).filter(Boolean);
});

const filteredCountries = computed(() => {
  const q = countryQuery.value.trim().toLowerCase();
  if (q.length < 2) return [];

  return allCountries.value
    .filter((country) => {
      const name = String(country.name || '').toLowerCase();
      const code = String(country.code || '').toLowerCase();
      return name.includes(q) || code.includes(q);
    })
    .slice(0, 10);
});

const activeContinentMeta = computed(() =>
  continents.value.find((c) => c.code === activeContinent.value) || null
);

const autoLabelShort = computed(() => {
  if (visitor.value?.city) {
    return visitor.value.city;
  }
  if (visitor.value?.country) {
    return visitor.value.country;
  }
  return t('market_auto');
});

const selectedLabel = computed(() => {
  const sel = props.modelValue;
  if (!sel || sel.mode === 'auto') {
    if (visitor.value?.city && visitor.value?.country) {
      return t('market_selected_auto_named', {
        city: visitor.value.city,
        country: visitor.value.country,
      });
    }
    return t('market_selected_auto');
  }
  if (sel.mode === 'global') {
    return t('market_global');
  }
  const codes = selectedCountryCodes.value;
  if (codes.length === 1) {
    return findCountryName(codes[0]) || codes[0];
  }
  if (codes.length > 1) {
    const names = codes.slice(0, 2).map((c) => findCountryName(c) || c);
    if (codes.length === 2) {
      return names.join(', ');
    }
    return t('market_selected_multi', {
      list: names.join(', '),
      extra: codes.length - 2,
    });
  }
  if (sel.mode === 'continent' && sel.continentCode) {
    return t('market_whole_continent', {
      continent: findContinentName(sel.continentCode) || sel.continentCode,
    });
  }
  return t('market_change');
});

function readExpandedPreference() {
  try {
    return localStorage.getItem(PICKER_OPEN_KEY) === '1';
  } catch {
    return false;
  }
}

function persistExpanded(value) {
  try {
    localStorage.setItem(PICKER_OPEN_KEY, value ? '1' : '0');
  } catch {
    // ignore storage errors
  }
}

function toggleExpanded() {
  expanded.value = !expanded.value;
  persistExpanded(expanded.value);
  if (expanded.value) {
    syncActiveContinent();
  }
}

function collapse() {
  expanded.value = false;
  persistExpanded(false);
  searchFocused.value = false;
}

function findCountryName(code) {
  const normalized = String(code || '').toUpperCase();
  for (const continent of continents.value) {
    const match = continent.countries?.find((c) => String(c.code).toUpperCase() === normalized);
    if (match) return match.name;
  }
  return null;
}

function findContinentName(code) {
  const normalized = String(code || '').toUpperCase();
  return continents.value.find((c) => String(c.code).toUpperCase() === normalized)?.name || null;
}

function syncActiveContinent() {
  const sel = props.modelValue;
  if (sel?.mode === 'continent' && sel.continentCode) {
    activeContinent.value = sel.continentCode;
    return;
  }
  const codes = selectedCountryCodes.value;
  if (codes.length) {
    const match = continents.value.find((continent) =>
      continent.countries?.some(
        (c) => codes.includes(String(c.code).toUpperCase())
      )
    );
    if (match) {
      activeContinent.value = match.code;
      return;
    }
  }
  if (!activeContinent.value && continents.value.length) {
    activeContinent.value = continents.value.find((c) => c.code === 'EU')?.code
      || continents.value[0].code;
  }
}

function emitSelection(selection) {
  emit('update:modelValue', selection);
}

function selectAuto() {
  countryQuery.value = '';
  selectionLimitHit.value = false;
  emitSelection({ mode: 'auto', countryCode: null, countryCodes: [], continentCode: null });
  collapse();
}

function selectGlobal() {
  countryQuery.value = '';
  selectionLimitHit.value = false;
  emitSelection({ mode: 'global', countryCode: null, countryCodes: [], continentCode: null });
  collapse();
}

function selectContinent(code, name) {
  activeContinent.value = code;
  countryQuery.value = '';
  selectionLimitHit.value = false;
  emitSelection({
    mode: 'continent',
    countryCode: null,
    countryCodes: [],
    continentCode: code,
    continentName: name,
  });
  collapse();
}

function emitCountriesSelection(codes) {
  const normalized = [...new Set(codes.map((c) => String(c).toUpperCase()))];
  if (normalized.length === 0) {
    selectAuto();
    return;
  }
  if (normalized.length === 1) {
    emitSelection({
      mode: 'country',
      countryCode: normalized[0],
      countryCodes: normalized,
      continentCode: null,
    });
    return;
  }
  emitSelection({
    mode: 'countries',
    countryCode: null,
    countryCodes: normalized,
    continentCode: null,
  });
}

function toggleCountry(code, name = null, continentCode = null) {
  const normalized = String(code).toUpperCase();
  if (continentCode) {
    activeContinent.value = continentCode;
  }

  let codes = [...selectedCountryCodes.value];
  const idx = codes.indexOf(normalized);
  if (idx >= 0) {
    codes.splice(idx, 1);
    selectionLimitHit.value = false;
  } else {
    if (codes.length >= MAX_SELECTED) {
      selectionLimitHit.value = true;
      return;
    }
    codes.push(normalized);
    selectionLimitHit.value = false;
  }

  if (name && codes.includes(normalized)) {
    countryQuery.value = codes.length === 1 ? name : '';
  }
  searchFocused.value = false;
  emitCountriesSelection(codes);
}

function clearCountries() {
  countryQuery.value = '';
  selectionLimitHit.value = false;
  emitSelection({ mode: 'auto', countryCode: null, countryCodes: [], continentCode: null });
}

function pickSearchCountry(country) {
  toggleCountry(country.code, country.name, country.continentCode);
}

function setActiveContinent(code) {
  activeContinent.value = activeContinent.value === code ? null : code;
}

function onSearchBlur() {
  window.setTimeout(() => {
    searchFocused.value = false;
  }, 120);
}

function isCountrySelected(code) {
  return selectedCountryCodes.value.includes(String(code).toUpperCase());
}

function isWholeContinentSelected(code) {
  return props.modelValue.mode === 'continent'
    && String(props.modelValue.continentCode || '').toUpperCase() === String(code).toUpperCase();
}

async function loadCatalog() {
  loadingCatalog.value = true;
  try {
    const data = await api.getMarkets(locale.value);
    continents.value = data?.continents || [];
    visitor.value = data?.visitor || null;
    syncActiveContinent();
  } catch {
    continents.value = [];
  } finally {
    loadingCatalog.value = false;
  }
}

onMounted(loadCatalog);

watch(locale, loadCatalog);

watch(
  () => props.modelValue,
  (sel) => {
    const codes = selectedCountryCodes.value;
    if (codes.length === 1) {
      const name = findCountryName(codes[0]);
      if (name) countryQuery.value = name;
    } else if (!sel || sel.mode === 'auto' || sel.mode === 'global' || sel.mode === 'continent') {
      countryQuery.value = '';
    } else if (codes.length > 1) {
      countryQuery.value = '';
    }
    syncActiveContinent();
  },
  { immediate: true, deep: true }
);
</script>
