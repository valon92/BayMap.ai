<template>
  <article class="px-4 pb-24">
    <div class="max-w-3xl mx-auto pt-4 sm:pt-10">
      <router-link
        to="/"
        class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-blue-600 transition-colors mb-8 font-medium"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        {{ t('how.back_home') }}
      </router-link>

      <header class="text-center flex flex-col items-center mb-12 sm:mb-14">
        <p class="hero-badge mb-5">
          <span class="relative flex h-2 w-2">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-500 opacity-50" />
            <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-600" />
          </span>
          {{ t('hero_ai_badge') }}
        </p>
        <h1 class="text-2xl sm:text-4xl font-bold text-slate-900 tracking-tight mb-4">
          {{ t('how.title') }}
        </h1>
        <p class="text-slate-400 text-base sm:text-lg leading-relaxed max-w-2xl mx-auto">
          {{ t('how.subtitle') }}
        </p>
      </header>

      <section class="glass-card p-6 sm:p-8 mb-6">
        <h2 class="text-lg font-semibold text-slate-900 mb-3">{{ t('how.what_title') }}</h2>
        <p class="text-slate-400 leading-relaxed">{{ t('how.what_body') }}</p>
      </section>

      <section class="mb-6">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-violet-600 mb-4 px-1">
          {{ t('how.principles_title') }}
        </h2>
        <div class="grid sm:grid-cols-2 gap-3">
          <div
            v-for="key in principleKeys"
            :key="key"
            class="glass-card p-4 text-sm text-slate-400 leading-relaxed"
          >
            <span class="text-blue-600 font-medium">{{ t(`how.principles.${key}`) }}</span>
          </div>
        </div>
      </section>

      <section class="mb-6">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-blue-600 mb-4 px-1">
          {{ t('how.pipeline_title') }}
        </h2>
        <ol class="space-y-3">
          <li
            v-for="(step, i) in steps"
            :key="step.key"
            class="how-step glass-card p-5 sm:p-6 flex gap-4 sm:gap-5"
          >
            <div class="how-step-num" :class="step.accent">{{ i + 1 }}</div>
            <div class="min-w-0 flex-1">
              <h3 class="font-semibold text-slate-900 mb-1.5">{{ t(`how.steps.${step.key}.title`) }}</h3>
              <p class="text-sm text-slate-400 leading-relaxed">{{ t(`how.steps.${step.key}.body`) }}</p>
            </div>
          </li>
        </ol>
      </section>

      <section class="glass-card p-6 sm:p-8 mb-6">
        <h2 class="text-lg font-semibold text-slate-900 mb-3">{{ t('how.serve_title') }}</h2>
        <p class="text-slate-400 leading-relaxed mb-5">{{ t('how.serve_intro') }}</p>
        <ul class="grid sm:grid-cols-2 gap-3">
          <li
            v-for="item in serveItems"
            :key="item"
            class="flex items-start gap-3 rounded-xl bg-slate-50 border border-slate-200 px-4 py-3.5"
          >
            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md bg-blue-50 text-blue-600">
              <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
            </span>
            <span class="text-sm text-slate-700">{{ t(`how.serve_items.${item}`) }}</span>
          </li>
        </ul>
      </section>

      <section class="mb-6">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-violet-600 mb-4 px-1">
          {{ t('how.features_title') }}
        </h2>
        <div class="grid sm:grid-cols-2 gap-3">
          <div
            v-for="feat in features"
            :key="feat.key"
            class="how-feature glass-card p-5"
          >
            <div class="how-feature-icon" :class="feat.accent">
              <component :is="feat.icon" />
            </div>
            <h3 class="font-semibold text-slate-900 text-sm mb-1.5">{{ t(`how.features.${feat.key}.title`) }}</h3>
            <p class="text-xs text-slate-500 leading-relaxed">{{ t(`how.features.${feat.key}.body`) }}</p>
          </div>
        </div>
      </section>

      <section class="glass-card p-6 sm:p-8 mb-6">
        <h2 class="text-lg font-semibold text-slate-900 mb-2">{{ t('how.filter_examples_title') }}</h2>
        <p class="text-slate-400 text-sm mb-4">{{ t('how.filter_examples_intro') }}</p>
        <ul class="space-y-2 text-sm">
          <li
            v-for="cat in filterExampleKeys"
            :key="cat"
            class="flex gap-2 text-slate-400"
          >
            <span class="text-blue-600 font-medium shrink-0">{{ t(`categories.${cat}`) }}:</span>
            <span>{{ t(`how.filter_examples.${cat}`) }}</span>
          </li>
        </ul>
      </section>

      <section class="glass-card p-6 sm:p-8 mb-10">
        <h2 class="text-lg font-semibold text-slate-900 mb-3">{{ t('how.categories_title') }}</h2>
        <p class="text-slate-400 text-sm mb-4">{{ t('how.categories_body') }}</p>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="cat in categoryKeys"
            :key="cat"
            class="px-3 py-1.5 rounded-full text-xs font-medium text-slate-700 bg-slate-50 border border-slate-200"
          >
            {{ t(`categories.${cat}`) }}
          </span>
        </div>
      </section>

      <div class="text-center glass rounded-2xl p-8 border border-blue-200 bg-gradient-to-b from-blue-50 to-white">
        <p class="text-slate-600 mb-6 max-w-md mx-auto">{{ t('how.cta_text') }}</p>
        <router-link to="/" class="btn-primary">
          {{ t('how.cta_search') }}
        </router-link>
      </div>
    </div>
  </article>
</template>

<script setup>
import { h, inject } from 'vue';

const { t } = inject('i18n');

const steps = [
  { key: 'describe', accent: 'how-step-num--sky' },
  { key: 'parse', accent: 'how-step-num--violet' },
  { key: 'expand', accent: 'how-step-num--violet' },
  { key: 'geo', accent: 'how-step-num--emerald' },
  { key: 'search', accent: 'how-step-num--emerald' },
  { key: 'rank', accent: 'how-step-num--amber' },
  { key: 'filters', accent: 'how-step-num--amber' },
];

const principleKeys = ['describe', 'stateless', 'local', 'ranked'];
const filterExampleKeys = ['car', 'book', 'painting', 'electronics', 'fashion'];

const serveItems = ['intent', 'marketplaces', 'ranking', 'links', 'locale', 'privacy'];

const categoryKeys = [
  'car', 'book', 'electronics', 'furniture', 'fashion', 'luxury', 'real_estate', 'marketplace',
];

const iconSearch = () =>
  h('svg', { class: 'w-5 h-5', fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor', 'stroke-width': '1.75' }, [
    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z' }),
  ]);

const iconSpark = () =>
  h('svg', { class: 'w-5 h-5', fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor', 'stroke-width': '1.75' }, [
    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z' }),
  ]);

const iconGlobe = () =>
  h('svg', { class: 'w-5 h-5', fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor', 'stroke-width': '1.75' }, [
    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m14.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.902m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418' }),
  ]);

const iconCamera = () =>
  h('svg', { class: 'w-5 h-5', fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor', 'stroke-width': '1.75' }, [
    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z' }),
    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z' }),
  ]);

const features = [
  { key: 'semantic', accent: 'how-feature-icon--sky', icon: iconSpark },
  { key: 'vision', accent: 'how-feature-icon--violet', icon: iconCamera },
  { key: 'geo', accent: 'how-feature-icon--emerald', icon: iconGlobe },
  { key: 'instant', accent: 'how-feature-icon--amber', icon: iconSearch },
];
</script>
