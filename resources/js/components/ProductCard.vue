<template>
  <article class="glass-card overflow-hidden flex flex-col h-full">
    <div class="relative aspect-[4/3] overflow-hidden bg-slate-900/50">
      <img
        :src="product.image"
        :alt="product.title"
        class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
        loading="lazy"
      />
      <div
        class="absolute top-3 right-3 px-2.5 py-1 rounded-lg text-xs font-bold backdrop-blur-md"
        :class="scoreClass"
      >
        {{ product.match_score }}% {{ t('match') }}
      </div>
      <span
        v-if="product.sponsored"
        class="absolute top-3 left-3 px-2 py-0.5 rounded text-[10px] uppercase tracking-wider bg-amber-500/90 text-black font-semibold"
      >
        Sponsored
      </span>
    </div>

    <div class="p-4 flex flex-col flex-1">
      <div class="flex items-start justify-between gap-2 mb-2">
        <h3 class="font-semibold text-white leading-snug line-clamp-2">{{ product.title }}</h3>
      </div>

      <p class="text-xl font-bold text-sky-400 mb-1">
        {{ formatPrice(product.price, product.currency) }}
      </p>
      <p class="text-xs text-slate-400 mb-3 flex items-center gap-1">
        <span>{{ product.location }}</span>
        <span class="text-slate-600">·</span>
        <span class="text-slate-300">{{ product.source }}</span>
      </p>

      <div class="mt-auto space-y-3">
        <div class="p-3 rounded-xl bg-violet-500/10 border border-violet-500/20">
          <p class="text-[10px] uppercase tracking-wider text-violet-300 mb-1">{{ t('why_ai') }}</p>
          <p class="text-xs text-slate-300 leading-relaxed">{{ product.ai_explanation }}</p>
        </div>

        <a
          :href="product.url"
          target="_blank"
          rel="noopener noreferrer sponsored"
          class="btn-primary w-full text-sm py-2.5"
        >
          {{ t('buy') }} →
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
  if (s >= 90) return 'bg-emerald-500/80 text-white';
  if (s >= 80) return 'bg-sky-500/80 text-white';
  return 'bg-slate-600/80 text-white';
});

function formatPrice(price, currency = 'EUR') {
  return new Intl.NumberFormat('en-EU', { style: 'currency', currency }).format(price);
}
</script>
