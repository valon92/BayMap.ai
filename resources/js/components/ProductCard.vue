<template>
  <article class="product-card group/card">
    <div class="relative aspect-square sm:aspect-[4/3] overflow-hidden bg-slate-100 shrink-0">
      <img
        :src="displayImage"
        :alt="displayTitle"
        class="h-full w-full transition-transform duration-300 group-hover/card:scale-[1.03]"
        :class="product.live ? 'object-contain p-2 bg-white' : 'object-cover'"
        loading="lazy"
        @error="onImageError"
      />
      <div class="absolute inset-0 bg-gradient-to-t from-slate-900/25 via-transparent to-transparent pointer-events-none" />

      <span
        class="absolute top-1.5 right-1.5 px-1 py-px rounded text-[9px] font-semibold tabular-nums backdrop-blur-md"
        :class="scoreClass"
      >
        {{ product.match_score }}%
      </span>

      <span
        v-if="product.live"
        class="absolute top-1.5 left-1.5 px-1 py-px rounded text-[8px] uppercase tracking-wide bg-emerald-500/90 text-black font-semibold"
      >
        Live
      </span>
      <span
        v-else-if="product.sponsored"
        class="absolute top-1.5 left-1.5 px-1 py-px rounded text-[8px] uppercase tracking-wide bg-amber-500/90 text-black font-semibold"
      >
        Ad
      </span>

      <span
        v-if="product.source"
        class="absolute bottom-1.5 left-1.5 max-w-[calc(100%-2.5rem)] truncate px-1.5 py-0.5 rounded-md text-[9px] font-medium bg-white/95 text-slate-700 shadow-sm border border-slate-200/80"
      >
        {{ product.source }}
      </span>
    </div>

    <div class="relative flex flex-col flex-1 p-2 gap-1 min-h-0 pr-9">
      <h3
        class="text-[11px] leading-[1.35] font-medium text-slate-800 line-clamp-2 tracking-tight"
        :title="displayTitle"
      >
        {{ displayTitle }}
      </h3>

      <div class="flex items-center gap-1.5 flex-wrap mt-auto">
        <p class="text-[13px] font-semibold text-blue-600 tabular-nums leading-none">
          {{ formatPrice(product.price, product.currency) }}
        </p>
        <span
          v-if="product.offer_count > 1"
          class="px-1 py-px rounded text-[8px] font-medium text-blue-700 bg-blue-50 border border-blue-100"
        >
          {{ product.offer_count }} {{ t('offers') }}
        </span>
      </div>

      <div
        v-if="hasMultipleOffers"
        class="mt-0.5"
      >
        <button
          type="button"
          class="text-[9px] font-medium text-blue-600 hover:text-blue-700 underline-offset-2 hover:underline"
          @click.stop="showOffers = !showOffers"
        >
          {{ showOffers ? t('hide_offers') : t('compare_prices') }}
        </button>
        <ul
          v-if="showOffers"
          class="mt-1 space-y-0.5 max-h-20 overflow-y-auto"
        >
          <li
            v-for="(offer, index) in product.offers"
            :key="`${offer.source_key || offer.source}-${index}`"
          >
            <a
              :href="offer.url"
              target="_blank"
              rel="noopener noreferrer sponsored"
              class="flex items-center justify-between gap-1 text-[9px] text-slate-600 hover:text-blue-600"
              @click.stop
            >
              <span class="truncate">{{ offer.source }}</span>
              <span class="shrink-0 font-medium tabular-nums">{{ formatPrice(offer.price, offer.currency) }}</span>
            </a>
          </li>
        </ul>
      </div>

      <p
        v-if="product.location"
        class="text-[9px] text-slate-500 truncate leading-tight"
      >
        {{ product.location }}
      </p>
    </div>

    <a
      :href="product.url"
      target="_blank"
      rel="noopener noreferrer sponsored"
      class="absolute bottom-2 right-2 z-10 flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-sm transition-all duration-200 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600 group-hover/card:translate-x-0.5"
      :aria-label="listingAriaLabel"
      @click.stop
    >
      <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
      </svg>
    </a>
  </article>
</template>

<script setup>
import { computed, inject, ref, watch } from 'vue';

const props = defineProps({
  product: { type: Object, required: true },
});

const { t } = inject('i18n');

const showOffers = ref(false);

const hasMultipleOffers = computed(() => {
  return Array.isArray(props.product.offers) && props.product.offers.length > 1;
});

const FALLBACK_IMAGE = 'https://images.unsplash.com/photo-1618843479313-40f8afb4b4d8?w=800&q=80';
const imageBroken = ref(false);

const displayImage = computed(() => {
  if (imageBroken.value || !props.product.image) {
    return FALLBACK_IMAGE;
  }

  return props.product.image;
});

function onImageError() {
  imageBroken.value = true;
}

watch(
  () => props.product?.id ?? props.product?.image,
  () => {
    imageBroken.value = false;
  },
);

const scoreClass = computed(() => {
  const s = props.product.match_score || 0;
  if (s >= 90) return 'bg-emerald-500 text-white';
  if (s >= 80) return 'bg-blue-600 text-white';
  return 'bg-slate-600 text-white';
});

const displayTitle = computed(() => stripStoreFromTitle(props.product.title, props.product.source));

const listingAriaLabel = computed(() => {
  const label = t('buy');
  return `${label}: ${displayTitle.value}`;
});

function stripStoreFromTitle(title, source) {
  if (!title) return '';
  let result = title.trim();
  const sourceKey = normalizeKey(source);

  for (const sep of [' — ', ' · ', ' - ']) {
    const idx = result.lastIndexOf(sep);
    if (idx <= 0) continue;

    const suffix = result.slice(idx + sep.length);
    const suffixKey = normalizeKey(suffix);

    if (!suffixKey) continue;

    const matchesSource =
      sourceKey &&
      (suffixKey.includes(sourceKey) ||
        sourceKey.includes(suffixKey) ||
        sharesToken(suffixKey, sourceKey));

    const looksLikeStore =
      /(merrjep|dyqani|pazar\s*3|gjirafa|neptun|aza\s*electronics|focus\s*electronics|pc\s*store|sparkle|tregu\.com|ebay|amazon|etsy|auto\s*scout|mobile\.de)/i.test(
        suffix
      );

    if (matchesSource || looksLikeStore) {
      result = result.slice(0, idx).trim();
      break;
    }
  }

  return result.replace(/\s·\s*#\d+\s*$/i, '').replace(/\s{2,}/g, ' ').trim();
}

function normalizeKey(value) {
  return String(value || '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '');
}

function sharesToken(a, b) {
  if (!a || !b || a.length < 4 || b.length < 4) return false;
  const min = Math.min(a.length, b.length, 8);
  return a.slice(0, min) === b.slice(0, min);
}

function formatPrice(price, currency = 'EUR') {
  return new Intl.NumberFormat('en-EU', { style: 'currency', currency }).format(price);
}
</script>
