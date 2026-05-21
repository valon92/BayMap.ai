<template>
  <div class="search-scope" :class="variant === 'embedded' ? 'search-scope--embedded' : ''">
    <div class="search-scope-header">
      <span class="search-scope-icon" aria-hidden="true">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
      </span>
      <span class="search-scope-label">{{ t('location_scope_title') }}</span>
    </div>

    <div class="scope-scroll" role="group" :aria-label="t('location_scope_title')">
      <div class="scope-scroll-track">
        <button
          v-for="opt in options"
          :key="opt.value"
          type="button"
          class="scope-chip"
          :class="{ 'scope-chip--active': modelValue === opt.value }"
          :disabled="disabled"
          @click="$emit('update:modelValue', opt.value)"
        >
          <span v-if="modelValue === opt.value" class="scope-chip-dot" aria-hidden="true" />
          {{ opt.label }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, inject } from 'vue';
import api from '../services/api';

defineProps({
  modelValue: { type: String, default: 'auto' },
  disabled: { type: Boolean, default: false },
  variant: { type: String, default: 'default' },
});

defineEmits(['update:modelValue']);

const { t } = inject('i18n');
const cityName = ref(null);

onMounted(async () => {
  try {
    const geo = await api.getGeo();
    cityName.value = geo?.city || null;
  } catch {
    cityName.value = null;
  }
});

const options = computed(() => [
  { value: 'auto', label: t('scope_auto') },
  { value: 'city', label: cityName.value ? t('scope_city_named', { city: cityName.value }) : t('scope_city') },
  { value: 'country', label: t('scope_country') },
  { value: 'region', label: t('scope_region') },
  { value: 'world', label: t('scope_world') },
]);
</script>
