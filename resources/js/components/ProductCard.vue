<template>
  <article
    class="product-card group/card flex flex-col h-full overflow-hidden rounded-xl border border-white/[0.08] bg-slate-900/50 backdrop-blur-sm transition-all duration-200 hover:border-sky-500/25 hover:bg-slate-900/70 hover:shadow-[0_8px_30px_-12px_rgba(56,189,248,0.25)]"
  >
    <div class="relative aspect-square sm:aspect-[4/3] overflow-hidden bg-slate-950/60">
      <img
        :src="product.image"
        :alt="product.title"
        class="h-full w-full object-cover transition-transform duration-300 group-hover/card:scale-[1.03]"
        loading="lazy"
      />
      <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent pointer-events-none" />

      <span
        class="absolute top-2 right-2 px-1.5 py-0.5 rounded-md text-[10px] font-bold backdrop-blur-md"
        :class="scoreClass"
      >
        {{ product.match_score }}%
      </span>

      <span
        v-if="product.live"
        class="absolute top-2 left-2 px-1.5 py-0.5 rounded text-[9px] uppercase tracking-wider bg-emerald-500/90 text-black font-semibold"
      >
        Live
      </span>
      <span
        v-else-if="product.sponsored"
        class="absolute top-2 left-2 px-1.5 py-0.5 rounded text-[9px] uppercase tracking-wider bg-amber-500/90 text-black font-semibold"
      >
        Ad
      </span>

      <span
        class="absolute bottom-2 left-2 max-w-[calc(100%-1rem)] truncate px-2 py-0.5 rounded-md text-[10px] font-medium bg-black/55 text-slate-200 backdrop-blur-sm border border-white/10"
      >
        {{ product.source }}
      </span>
    </div>

    <div class="flex flex-col flex-1 p-2.5 sm:p-3 gap-1.5 min-h-0">
      <h3 class="text-xs sm:text-sm font-medium text-white leading-snug line-clamp-2 min-h-[2.5rem]">
        {{ product.title }}
      </h3>

      <div class="flex items-baseline justify-between gap-2">
        <p class="text-base sm:text-lg font-bold text-sky-400 tabular-nums tracking-tight">
          {{ formatPrice(product.price, product.currency) }}
        </p>
        <span
          v-if="product.offer_count > 1"
          class="shrink-0 px-1.5 py-0.5 rounded text-[9px] font-medium bg-sky-500/15 text-sky-300 border border-sky-500/20"
        >
          {{ product.offer_count }} {{ t('offers') }}
        </span>
      </div>

      <p class="text-[10px] sm:text-[11px] text-slate-500 truncate">
        {{ product.location }}
      </p>

      <p
        v-if="product.meta_label"
        class="text-[10px] text-emerald-400/90 truncate"
        :title="product.meta_label"
      >
        {{ product.meta_label }}
      </p>

      <p
        v-if="product.ai_explanation"
        class="hidden lg:block text-[10px] text-slate-500 line-clamp-1 group-hover/card:line-clamp-2 transition-all"
        :title="product.ai_explanation"
      >
        <span class="text-violet-400/80 font-medium">{{ t('why_ai') }}:</span>
        {{ product.ai_explanation }}
      </p>

      <div
        v-if="alternateOffers.length"
        class="hidden xl:grid gap-1 opacity-0 max-h-0 overflow-hidden group-hover/card:opacity-100 group-hover/card:max-h-24 group-hover/card:py-1 transition-all duration-200"
      >
        <p class="text-[9px] uppercase tracking-wider text-slate-500">{{ t('compare_prices') }}</p>
        <div
          v-for="(offer, idx) in alternateOffers.slice(0, 2)"
          :key="idx"
          class="flex items-center justify-between text-[10px] gap-1"
        >
          <span class="text-slate-400 truncate">{{ offer.source }}</span>
          <span class="text-sky-400 tabular-nums shrink-0">{{ formatPrice(offer.price, offer.currency) }}</span>
        </div>
      </div>

      <div class="mt-auto pt-1">
        <a
          :href="product.url"
          target="_blank"
          rel="noopener noreferrer sponsored"
          class="flex items-center justify-center gap-1 w-full py-1.5 sm:py-2 rounded-lg text-[11px] sm:text-xs font-semibold text-white bg-gradient-to-r from-sky-600/90 to-violet-600/90 hover:from-sky-500 hover:to-violet-500 border border-white/10 transition-all active:scale-[0.98]"
        >
          {{ t('buy') }}
          <svg class="w-3 h-3 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
          </svg>
        </a>
      </div>
    </div>
  </article>
</template>

<script setup>
import { computed, inject } from 'vue';

const props = defineProps({
  product: { type: Object, required: true },
});

const { t } = inject('i18n');

const scoreClass = computed(() => {
  const s = props.product.match_score || 0;
  if (s >= 90) return 'bg-emerald-500/85 text-white';
  if (s >= 80) return 'bg-sky-500/85 text-white';
  return 'bg-slate-700/90 text-slate-200';
});

const alternateOffers = computed(() => {
  const offers = props.product.offers || [];
  if (offers.length <= 1) return [];
  return offers.slice(1, 4);
});

function formatPrice(price, currency = 'EUR') {
  return new Intl.NumberFormat('en-EU', { style: 'currency', currency }).format(price);
}
</script>
