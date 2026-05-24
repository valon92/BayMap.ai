<template>
  <div v-if="report?.length || pipeline?.length" class="glass rounded-xl p-4 mb-6 space-y-3">
    <h3 class="text-sm font-semibold text-white">{{ t('search_pipeline') }}</h3>

    <ul v-if="pipeline?.length" class="space-y-2">
      <li
        v-for="(step, i) in pipeline"
        :key="step.step"
        class="flex items-center gap-3 text-sm text-slate-300"
      >
        <span
          class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold"
          :class="step.status === 'completed' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-700 text-slate-400'"
        >
          {{ i + 1 }}
        </span>
        <span>{{ stepLabel(step) }}</span>
      </li>
    </ul>

    <div v-if="report?.length" class="flex flex-wrap gap-2 pt-1 border-t border-white/10">
      <span
        v-for="row in report"
        :key="row.source"
        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs"
        :class="badgeClass(row.mode)"
      >
        <span class="w-1.5 h-1.5 rounded-full" :class="dotClass(row.mode)" />
        {{ row.label || row.source }}
        <span class="opacity-70">({{ row.count }})</span>
      </span>
    </div>
  </div>
</template>

<script setup>
import { inject } from 'vue';

defineProps({
  pipeline: { type: Array, default: () => [] },
  report: { type: Array, default: () => [] },
});

const { t } = inject('i18n');

function stepLabel(step) {
  if (step.label) {
    return step.label;
  }
  const key = `pipeline_${step.step}`;
  const translated = t(key);
  return translated !== key ? translated : step.label;
}

function badgeClass(mode) {
  if (mode === 'live') return 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/20';
  if (mode === 'demo') return 'bg-amber-500/10 text-amber-200 border border-amber-500/20';
  if (mode === 'swiss_car_marketplace' || mode === 'dutch_car_marketplace' || mode === 'kosovo_marketplace') {
    return 'bg-sky-500/10 text-sky-200 border border-sky-500/25';
  }
  return 'bg-white/5 text-slate-500 border border-white/10';
}

function dotClass(mode) {
  if (mode === 'live') return 'bg-emerald-400';
  if (mode === 'demo') return 'bg-amber-400';
  return 'bg-slate-500';
}
</script>
