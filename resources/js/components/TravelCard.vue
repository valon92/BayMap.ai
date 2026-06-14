<template>
  <article class="travel-card group/travel">
    <div class="travel-card__main">
      <div class="travel-card__timeline">
        <div class="travel-card__time-block">
          <span class="travel-card__time">{{ product.departure_time || '—' }}</span>
          <span class="travel-card__code">{{ product.departure_airport || originCode }}</span>
        </div>

        <div class="travel-card__route-line" aria-hidden="true">
          <span class="travel-card__mode-icon">{{ modeIcon }}</span>
          <span class="travel-card__duration">{{ durationLabel }}</span>
          <span v-if="product.stops_label && product.stops_label !== 'Direct'" class="travel-card__stops">{{ product.stops_label }}</span>
        </div>

        <div class="travel-card__time-block travel-card__time-block--end">
          <span class="travel-card__time">{{ product.arrival_time || '—' }}</span>
          <span class="travel-card__code">{{ product.arrival_airport || destinationCode }}</span>
        </div>
      </div>

      <div class="travel-card__details">
        <div class="travel-card__header">
          <img
            v-if="product.image && product.travel_mode === 'flight'"
            :src="product.image"
            :alt="product.airline || product.source"
            class="travel-card__logo"
            loading="lazy"
            @error="logoBroken = true"
          />
          <div class="travel-card__titles">
            <h3 class="travel-card__title">{{ displayTitle }}</h3>
            <p v-if="subtitle" class="travel-card__subtitle">{{ subtitle }}</p>
          </div>
        </div>

        <div class="travel-card__meta">
          <span class="travel-chip">{{ modeLabel }}</span>
          <span v-if="product.stops_label" class="travel-chip">{{ product.stops_label }}</span>
          <span v-if="product.travel_class" class="travel-chip">{{ product.travel_class }}</span>
          <span v-if="product.departure_date" class="travel-chip">{{ product.departure_date }}</span>
          <span v-if="product.carbon_kg" class="travel-chip travel-chip--muted">{{ product.carbon_kg }} kg CO₂</span>
        </div>

        <p class="travel-card__route">{{ routeLabel }}</p>
      </div>

      <div class="travel-card__buy">
        <span
          class="travel-card__score"
          :class="scoreClass"
        >
          {{ product.match_score }}%
        </span>

        <p v-if="hasPrice" class="travel-card__price">
          {{ formatPrice(product.price, product.currency) }}
        </p>
        <p v-else class="travel-card__price travel-card__price--quote">
          {{ t('travel_check_price') }}
        </p>

        <a
          :href="product.url"
          target="_blank"
          rel="noopener noreferrer sponsored"
          class="travel-card__cta"
          @click.stop
        >
          {{ hasPrice ? t('travel_book') : t('travel_compare') }}
          <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
          </svg>
        </a>

        <p class="travel-card__source">
          <span class="travel-card__live">{{ t('live') }}</span>
          {{ product.source }}
        </p>
      </div>
    </div>
  </article>
</template>

<script setup>
import { computed, inject, ref } from 'vue';

const props = defineProps({
  product: { type: Object, required: true },
});

const { t } = inject('i18n');
const logoBroken = ref(false);

const displayTitle = computed(() => props.product.title || '');
const subtitle = computed(() => props.product.subtitle || props.product.flight_number || '');
const routeLabel = computed(() => props.product.location || `${props.product.origin_city || ''} → ${props.product.destination_city || ''}`);
const durationLabel = computed(() => props.product.duration_label || props.product.travel_duration || '—');
const hasPrice = computed(() => !props.product.price_on_request && Number(props.product.price) > 0);

const modeLabel = computed(() => {
  const mode = props.product.travel_mode || props.product.product_type || 'flight';
  return t(`travel_mode_${mode}`, mode);
});

const modeIcon = computed(() => {
  const mode = props.product.travel_mode || 'flight';
  return mode === 'train' ? '🚆' : mode === 'bus' ? '🚌' : '✈️';
});

const originCode = computed(() => props.product.departure_airport || '—');
const destinationCode = computed(() => props.product.arrival_airport || '—');

const scoreClass = computed(() => {
  const s = props.product.match_score || 0;
  if (s >= 90) return 'travel-card__score--high';
  if (s >= 75) return 'travel-card__score--mid';
  return 'travel-card__score--base';
});

function formatPrice(price, currency = 'EUR') {
  if (!price) return '';
  return new Intl.NumberFormat('en-EU', { style: 'currency', currency }).format(price);
}
</script>
