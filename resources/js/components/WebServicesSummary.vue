<template>
  <div v-if="parsed" class="web-services-summary">
    <div class="web-services-summary__head">
      <span class="web-services-summary__icon" aria-hidden="true">{{ primaryIcon }}</span>
      <div class="web-services-summary__titles">
        <p class="web-services-summary__eyebrow">{{ t('web_services_eyebrow') }}</p>
        <h2 v-if="domainName" class="web-services-summary__domain">
          <span class="web-services-summary__domain-base">{{ domainBase }}</span><span class="web-services-summary__domain-tld">{{ domainTld }}</span>
        </h2>
        <h2 v-else class="web-services-summary__domain web-services-summary__domain--generic">
          {{ primaryLabel }}
        </h2>
      </div>
    </div>

    <div class="web-services-summary__meta">
      <span
        v-for="type in serviceTypes"
        :key="type"
        class="web-chip"
        :class="{ 'web-chip--accent': type === serviceTypes[0] }"
      >
        {{ t(`web_services_mode_${type}`, type) }}
      </span>
      <span class="web-chip">{{ t('web_services_bridge_badge') }}</span>
      <span v-if="parsed.search_scope === 'universal'" class="web-chip">{{ t('web_services_scope_global') }}</span>
    </div>

    <p class="web-services-summary__hint">{{ hintText }}</p>
  </div>
</template>

<script setup>
import { computed, inject } from 'vue';

const props = defineProps({
  parsed: { type: Object, default: null },
});

const { t } = inject('i18n');

const SERVICE_ORDER = ['domain', 'hosting', 'email', 'ssl', 'website'];

const serviceTypes = computed(() => {
  const fromList = Array.isArray(props.parsed?.web_service_types)
    ? props.parsed.web_service_types.map(String)
    : [];

  if (fromList.length) {
    return SERVICE_ORDER.filter((type) => fromList.includes(type));
  }

  const single = String(props.parsed?.web_service_type || props.parsed?.product_type || 'domain');
  if (single === 'combo' || single === 'website') {
    return ['domain', 'hosting'];
  }

  return [single];
});

const primaryType = computed(() => serviceTypes.value[0] || 'domain');

const domainName = computed(() => (
  String(props.parsed?.domain_query || props.parsed?.domain_name || '').trim()
));

const domainParts = computed(() => {
  const name = domainName.value;
  const dot = name.lastIndexOf('.');
  if (dot <= 0) {
    return { base: name, tld: '' };
  }

  return {
    base: name.slice(0, dot),
    tld: name.slice(dot),
  };
});

const domainBase = computed(() => domainParts.value.base);
const domainTld = computed(() => domainParts.value.tld);

const primaryLabel = computed(() => {
  if (serviceTypes.value.length > 1) {
    return t('web_services_mode_combo');
  }

  return t(`web_services_mode_${primaryType.value}`, primaryType.value);
});

const primaryIcon = computed(() => {
  if (serviceTypes.value.length > 1) {
    return '🌐';
  }

  const icons = {
    domain: '🌐',
    hosting: '🖥️',
    email: '✉️',
    ssl: '🔒',
    website: '🔗',
  };

  return icons[primaryType.value] || '🌐';
});

const hintText = computed(() => {
  if (serviceTypes.value.length > 1) {
    return t('web_services_bridge_hint_combo');
  }

  const key = `web_services_bridge_hint_${primaryType.value}`;
  const specific = t(key, '');
  if (specific && specific !== key) {
    return specific;
  }

  return t('web_services_bridge_hint');
});
</script>
