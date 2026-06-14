<template>
  <div v-if="parsed" class="travel-route-summary">
    <div class="travel-route-summary__route">
      <span class="travel-route-summary__city">{{ parsed.origin_city || '—' }}</span>
      <span class="travel-route-summary__arrow">→</span>
      <span class="travel-route-summary__city">{{ parsed.destination_city || parsed.destination || '—' }}</span>
    </div>
    <div class="travel-route-summary__meta">
      <span v-if="parsed.departure_date">{{ parsed.departure_date }}</span>
      <span v-if="timeWindow">{{ timeWindow }}</span>
      <span v-if="parsed.travelers">{{ parsed.travelers }} {{ t('travelers') }}</span>
      <span v-if="parsed.travel_type">{{ t(`travel_type_${parsed.travel_type}`, parsed.travel_type) }}</span>
    </div>
    <p class="travel-route-summary__hint">{{ hintText }}</p>
  </div>
</template>

<script setup>
import { computed, inject } from 'vue';

const props = defineProps({
  parsed: { type: Object, default: null },
});

const { t } = inject('i18n');

const timeWindow = computed(() => {
  const from = props.parsed?.departure_time_from;
  const to = props.parsed?.departure_time_to;
  if (!from && !to) return '';
  if (from && to) return `${from} – ${to}`;
  return from || to;
});

const isLongHaul = computed(() => {
  const origin = String(props.parsed?.origin_country_code || '').toUpperCase();
  const destination = String(props.parsed?.destination_country_code || '').toUpperCase();
  if (!origin || !destination || origin === destination) return false;
  const regions = { US: 'NA', CA: 'NA', DE: 'EU', FR: 'EU', GB: 'EU', CH: 'EU', IT: 'EU', XK: 'EU', AL: 'EU' };

  return (regions[origin] || origin) !== (regions[destination] || destination);
});

const hintText = computed(() => (
  isLongHaul.value ? t('travel_bridge_hint_longhaul') : t('travel_bridge_hint')
));
</script>
