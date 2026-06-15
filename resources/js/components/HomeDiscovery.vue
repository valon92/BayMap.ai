<template>
  <section class="discovery-section" aria-labelledby="discovery-heading">
    <div class="discovery-section__intro">
      <h2 id="discovery-heading" class="discovery-section__title">{{ t('discovery_title') }}</h2>
      <p class="discovery-section__subtitle">{{ t('discovery_subtitle') }}</p>
    </div>

    <div v-if="examplesLoading" class="discovery-grid">
      <div v-for="n in 9" :key="n" class="discovery-card discovery-card--skeleton skeleton" />
    </div>

    <div v-else class="discovery-grid">
      <button
        v-for="(ex, i) in examples"
        :key="ex.id || i"
        type="button"
        class="discovery-card group"
        @click="$emit('select', localizedExample(ex))"
      >
        <span class="discovery-card__icon" aria-hidden="true">{{ ex.icon || '🔍' }}</span>
        <span class="discovery-card__category">{{ categoryLabel(ex.category) }}</span>
        <span class="discovery-card__query">{{ localizedExample(ex) }}</span>
        <span class="discovery-card__cta">
          {{ t('discovery_try') }}
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
          </svg>
        </span>
      </button>
    </div>

    <div v-if="trending.length" class="discovery-trending">
      <div class="discovery-trending__header">
        <h3 class="discovery-trending__title">{{ t('trending_title') }}</h3>
        <p class="discovery-trending__hint">{{ t('trending_subtitle') }}</p>
      </div>

      <div v-if="trendingLoading" class="discovery-trending__list">
        <div v-for="n in 4" :key="n" class="discovery-trending__row skeleton h-12 rounded-xl" />
      </div>

      <div v-else class="discovery-trending__list">
        <button
          v-for="item in trending"
          :key="item.query + (item.sq || '')"
          type="button"
          class="discovery-trending__row group"
          @click="$emit('select', localizedTrending(item))"
        >
          <span class="discovery-trending__icon" aria-hidden="true">{{ trendingIcon(item.category) }}</span>
          <span class="discovery-trending__text">
            <span class="discovery-trending__query">{{ localizedTrending(item) }}</span>
            <span class="discovery-trending__category">{{ categoryLabel(item.category) }}</span>
          </span>
          <span class="discovery-trending__count">{{ formatCount(item.count) }}</span>
        </button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, onMounted, inject } from 'vue';
import api from '../services/api';

defineEmits(['select']);

const { t, locale } = inject('i18n');
const examples = ref([]);
const trending = ref([]);
const examplesLoading = ref(true);
const trendingLoading = ref(true);

const CATEGORY_ICONS = {
  automotive: '🚗',
  car: '🚗',
  travel: '✈️',
  book: '📚',
  painting: '🎨',
  art: '🎨',
  electronics: '💻',
  furniture: '🛋️',
  luxury: '⌚',
  fashion: '👟',
};

onMounted(async () => {
  try {
    const data = await api.getExamples();
    examples.value = data.examples || [];
  } catch {
    examples.value = [];
  } finally {
    examplesLoading.value = false;
  }

  try {
    const data = await api.getTrending();
    trending.value = data.trending || [];
  } catch {
    trending.value = [];
  } finally {
    trendingLoading.value = false;
  }
});

function localizedExample(ex) {
  return locale.value === 'sq' ? (ex.sq || ex.en) : (ex.en || ex.sq);
}

function localizedTrending(item) {
  if (locale.value === 'sq' && item.sq) {
    return item.sq;
  }

  return item.query;
}

function categoryLabel(category) {
  const key = String(category || 'marketplace').toLowerCase();
  return t(`example_category_${key}`, key);
}

function trendingIcon(category) {
  return CATEGORY_ICONS[String(category || '').toLowerCase()] || '🔥';
}

function formatCount(n) {
  if (n >= 1000) {
    return `${(n / 1000).toFixed(1).replace(/\.0$/, '')}k`;
  }

  return String(n ?? '');
}
</script>
