<template>
  <article class="web-services-card group/web">
    <div class="web-services-card__main">
      <div
        class="web-services-card__brand"
        :style="{ '--brand-color': brandColor, '--brand-bg': brandBg }"
      >
        <img
          v-if="logoUrl && !logoBroken"
          :src="logoUrl"
          :alt="providerName"
          class="web-services-card__logo"
          loading="lazy"
          @error="logoBroken = true"
        />
        <span v-else class="web-services-card__brand-letter" aria-hidden="true">{{ brandInitial }}</span>
      </div>

      <div class="web-services-card__body">
        <div class="web-services-card__header">
          <h3 class="web-services-card__title">{{ providerName }}</h3>
          <span v-if="product.live" class="web-services-card__live">{{ t('live') }}</span>
          <span v-if="rankLabel" class="web-services-card__rank">{{ rankLabel }}</span>
        </div>

        <p v-if="domainLabel" class="web-services-card__domain">{{ domainLabel }}</p>
        <p v-if="subtitle" class="web-services-card__subtitle">{{ subtitle }}</p>

        <div class="web-services-card__meta">
          <span class="web-chip web-chip--sm">{{ modeLabel }}</span>
          <span class="web-chip web-chip--sm web-chip--muted">{{ t('web_services_official_platform') }}</span>
        </div>
      </div>

      <div class="web-services-card__action">
        <p v-if="hasPrice" class="web-services-card__price">
          {{ displayPrice }}
        </p>
        <p v-else-if="marketplacePrice" class="web-services-card__price web-services-card__price--quote">
          {{ t('web_services_marketplace_price') }}
        </p>
        <p v-else class="web-services-card__price web-services-card__price--quote">
          {{ t('web_services_free_tier') }}
        </p>
        <p v-if="billingHint" class="web-services-card__billing">{{ billingHint }}</p>

        <a
          :href="product.url"
          target="_blank"
          rel="noopener noreferrer sponsored"
          class="web-services-card__cta"
          @click.stop
        >
          {{ t('web_services_visit') }}
          <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
          </svg>
        </a>
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

const serviceType = computed(() => (
  props.product.web_service_type || props.product.product_type || 'domain'
));

const modeLabel = computed(() => t(`web_services_mode_${serviceType.value}`, serviceType.value));

const domainLabel = computed(() => {
  const domain = String(props.product.domain_name || '').trim();
  if (!domain || serviceType.value !== 'domain') {
    return '';
  }

  return domain;
});

const providerName = computed(() => {
  const source = String(props.product.source || '').trim();
  if (source) {
    return source;
  }

  return String(props.product.title || '');
});

const subtitle = computed(() => {
  const sub = String(props.product.subtitle || '').trim();
  if (!sub || sub === domainLabel.value) {
    return '';
  }

  return sub;
});

const logoUrl = computed(() => (
  String(props.product.logo_url || props.product.image || '').trim()
));

const brandColor = computed(() => String(props.product.brand_color || '#4F46E5'));
const brandBg = computed(() => String(props.product.brand_bg || 'rgba(79, 70, 229, 0.1)'));

const brandInitial = computed(() => providerName.value.charAt(0).toUpperCase() || '?');

const rankLabel = computed(() => {
  const rank = Number(props.product.provider_rank || 0);
  if (!rank || rank > 3) {
    return '';
  }

  return `#${rank}`;
});

const hasPrice = computed(() => Number(props.product.price) > 0 && !props.product.price_on_request);

const marketplacePrice = computed(() => (
  String(props.product.price_label || '').toLowerCase() === 'marketplace'
));

const displayPrice = computed(() => {
  if (props.product.price_label && props.product.price_label !== 'marketplace') {
    return localizePriceLabel(String(props.product.price_label));
  }

  const price = Number(props.product.price);
  const currency = props.product.currency || 'EUR';
  if (price <= 0) {
    return '';
  }

  const period = props.product.billing_period === 'monthly'
    ? t('web_services_per_month')
    : t('web_services_per_year');

  return `${t('web_services_from')} ${formatMoney(price, currency)}${period}`;
});

const billingHint = computed(() => {
  if (!hasPrice.value) {
    return '';
  }

  return props.product.billing_period === 'monthly'
    ? t('web_services_billed_monthly')
    : t('web_services_billed_yearly');
});

function formatMoney(amount, currency) {
  try {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount);
  } catch {
    return `€${amount.toFixed(2)}`;
  }
}

function localizePriceLabel(label) {
  return label
    .replace(/\/yr\b/i, t('web_services_per_year'))
    .replace(/\/mo\b/i, t('web_services_per_month'))
    .replace(/^from /i, `${t('web_services_from')} `);
}
</script>
