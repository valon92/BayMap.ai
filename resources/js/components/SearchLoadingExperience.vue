<template>
  <div class="search-loading" role="status" :aria-label="t('search_loading_aria')" :aria-busy="true">
    <div class="search-loading__panel">
      <div class="search-loading__mesh" aria-hidden="true" />
      <div class="search-loading__scan-beam" aria-hidden="true" />

      <header class="search-loading__header">
        <div class="search-loading__brand">
          <span class="search-loading__live" aria-hidden="true">
            <span class="search-loading__live-ping" />
            <span class="search-loading__live-core" />
          </span>
          <span class="search-loading__brand-name">BuyMap.ai</span>
          <span class="search-loading__brand-sep" aria-hidden="true">·</span>
          <span class="search-loading__brand-valon">Valon AI</span>
        </div>

        <div class="search-loading__ring" aria-hidden="true">
          <svg viewBox="0 0 40 40" class="search-loading__ring-svg">
            <circle class="search-loading__ring-track" cx="20" cy="20" r="16" />
            <circle
              class="search-loading__ring-progress"
              cx="20"
              cy="20"
              r="16"
              :stroke-dasharray="ringCircumference"
              :stroke-dashoffset="ringOffset"
            />
          </svg>
          <span class="search-loading__ring-value">{{ progress }}%</span>
        </div>
      </header>

      <div class="search-loading__phase">
        <p class="search-loading__phase-kicker">{{ t('search_loading_kicker') }}</p>
        <h2 class="search-loading__phase-title">{{ activeStepLabel }}</h2>
        <p class="search-loading__phase-hint">{{ activeStepHint }}</p>
      </div>

      <div v-if="contextChips.length" class="search-loading__chips" aria-label="Query context">
        <span
          v-for="chip in contextChips"
          :key="chip.key"
          class="search-loading__chip"
          :class="`search-loading__chip--${chip.tone}`"
        >
          {{ chip.label }}
        </span>
      </div>

      <ol class="search-loading__pipeline">
        <li
          v-for="(step, idx) in steps"
          :key="step.id"
          class="search-loading__pipe"
          :class="{
            'search-loading__pipe--done': idx < activeStepIndex,
            'search-loading__pipe--active': idx === activeStepIndex,
          }"
        >
          <span class="search-loading__pipe-node" aria-hidden="true">
            <svg v-if="idx < activeStepIndex" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
            </svg>
            <span v-else-if="idx === activeStepIndex" class="search-loading__pipe-pulse" />
            <span v-else class="search-loading__pipe-idle" />
          </span>
          <span class="search-loading__pipe-label">{{ step.label }}</span>
          <span v-if="idx < steps.length - 1" class="search-loading__pipe-connector" aria-hidden="true" />
        </li>
      </ol>

      <div v-if="workerPreviews.length" class="search-loading__workers">
        <div class="search-loading__workers-head">
          <p class="search-loading__workers-title">{{ t('valon_workers') }}</p>
          <span class="search-loading__workers-badge">{{ t('search_loading_parallel') }}</span>
        </div>

        <div class="search-loading__workers-grid">
          <article
            v-for="(worker, idx) in workerPreviews"
            :key="worker.id"
            class="search-loading__worker"
            :class="workerStateClass(idx)"
          >
            <div class="search-loading__worker-top">
              <span class="search-loading__worker-avatar" aria-hidden="true">{{ worker.initial }}</span>
              <div class="search-loading__worker-meta">
                <span class="search-loading__worker-id">{{ worker.id }}</span>
                <span class="search-loading__worker-name">{{ worker.name }}</span>
              </div>
              <span class="search-loading__worker-badge">{{ workerStatusLabel(idx) }}</span>
            </div>
            <div class="search-loading__worker-bar" aria-hidden="true">
              <span class="search-loading__worker-bar-fill" :style="{ width: workerBarWidth(idx) }" />
            </div>
          </article>
        </div>
      </div>

      <p class="search-loading__footer">{{ t('footer') }}</p>
    </div>

    <ResultsSkeleton class="search-loading__skeleton" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, inject } from 'vue';
import ResultsSkeleton from './ResultsSkeleton.vue';

const props = defineProps({
  query: { type: String, default: '' },
});

const { t } = inject('i18n');

const progress = ref(6);
const activeStepIndex = ref(0);
const activeWorkerIndex = ref(-1);

const ringCircumference = 2 * Math.PI * 16;

let progressTimer = null;
let stepTimer = null;
let workerTimer = null;

const steps = computed(() => [
  { id: 'analyze', label: t('search_step_analyze'), hint: t('search_loading_hint_analyze') },
  { id: 'workers', label: t('search_step_workers'), hint: t('search_loading_hint_workers') },
  { id: 'federated', label: t('search_step_federated'), hint: t('search_loading_hint_federated') },
  { id: 'rank', label: t('search_step_rank'), hint: t('search_loading_hint_rank') },
]);

const activeStepLabel = computed(() => steps.value[activeStepIndex.value]?.label ?? t('searching'));
const activeStepHint = computed(() => steps.value[activeStepIndex.value]?.hint ?? t('search_loading_hint'));

const ringOffset = computed(() => ringCircumference - (progress.value / 100) * ringCircumference);

const workerPreviews = computed(() => {
  const q = (props.query || '').toLowerCase();
  const kosovo = /\b(kosov|kosove|prishtin|xk)\b/u.test(q);
  const fashion = /\b(nike|puma|adidas|patika|këpuc|kepuce|mabthje|mbathje|meshkuj|atlete|fashion|sneaker)\b/u.test(q);

  if (kosovo && fashion) {
    return [
      { id: 'ValonWorker-1', name: 'Melodia Px', initial: 'MP' },
      { id: 'ValonWorker-2', name: 'Driloni Sportswear', initial: 'DS' },
    ];
  }

  if (kosovo) {
    return [
      { id: 'ValonWorker-1', name: 'MerrJep Kosovo', initial: 'MJ' },
      { id: 'ValonWorker-2', name: 'Gjirafa50', initial: 'G5' },
    ];
  }

  return [
    { id: 'ValonWorker-1', name: t('search_worker_marketplace'), initial: 'MK' },
    { id: 'ValonWorker-2', name: t('search_worker_web'), initial: 'WB' },
  ];
});

const contextChips = computed(() => {
  const q = (props.query || '').toLowerCase();
  const chips = [];

  const priceMatch = q.match(/\b(?:deri|max|up to)\s*(?:ne|në|to)?\s*(\d+)\s*(?:euro|eur|€)\b/u);
  const priceValue = priceMatch?.[1] ?? null;
  if (priceValue) {
    chips.push({ key: 'price', label: `≤ ${priceValue} EUR`, tone: 'emerald' });
  }

  const brandMatch = q.match(/\b(nike|puma|adidas|reebok|new balance|under armour|timberland)\b/u);
  if (brandMatch) {
    chips.push({ key: 'brand', label: brandMatch[1], tone: 'accent' });
  }

  const sizeMatch = q.match(/\b(?:numer|nr|madh[eë]sia|size|no\.?)\s*(\d{2}(?:\.\d)?)\b/u);
  if (sizeMatch && sizeMatch[1] !== priceValue) {
    chips.push({ key: 'size', label: `${t('parsed_fields.size')}: ${sizeMatch[1]}`, tone: 'sky' });
  }

  if (/\b(qant[aë]?|çant[aë]?|cant[aë]?|backpack|bag)\b/u.test(q)) {
    chips.push({ key: 'product', label: t('search_loading_chip_bag'), tone: 'accent' });
  }

  if (/\b(shkoll[aë]?|school)\b/u.test(q)) {
    chips.push({ key: 'school', label: t('search_loading_chip_school'), tone: 'sky' });
  }

  if (/\b(femij|fëmij|kids|children)\b/u.test(q)) {
    chips.push({ key: 'kids', label: t('search_loading_chip_kids'), tone: 'sky' });
  }

  if (/\b(kosov|kosove|prishtin|xk)\b/u.test(q)) {
    chips.push({ key: 'geo', label: t('search_loading_chip_kosovo'), tone: 'violet' });
  }

  return chips;
});

function workerStateClass(idx) {
  if (idx < activeWorkerIndex.value) return 'search-loading__worker--done';
  if (idx === activeWorkerIndex.value) return 'search-loading__worker--active';
  return 'search-loading__worker--queued';
}

function workerStatusLabel(idx) {
  if (idx < activeWorkerIndex.value) return t('search_worker_done');
  if (idx === activeWorkerIndex.value) return t('search_worker_live');
  return t('search_worker_queued');
}

function workerBarWidth(idx) {
  if (idx < activeWorkerIndex.value) return '100%';
  if (idx === activeWorkerIndex.value) return `${Math.min(88, Math.max(24, progress.value))}%`;
  return '0%';
}

function clearTimers() {
  if (progressTimer) clearInterval(progressTimer);
  if (stepTimer) clearInterval(stepTimer);
  if (workerTimer) clearInterval(workerTimer);
}

onMounted(() => {
  progressTimer = setInterval(() => {
    if (progress.value < 94) {
      const bump = progress.value < 35 ? 4 : progress.value < 70 ? 2 : 1;
      progress.value = Math.min(94, progress.value + bump);
    }
  }, 650);

  stepTimer = setInterval(() => {
    if (activeStepIndex.value < steps.value.length - 1) {
      activeStepIndex.value += 1;
    }
  }, 4200);

  workerTimer = setInterval(() => {
    if (activeWorkerIndex.value < workerPreviews.value.length - 1) {
      activeWorkerIndex.value += 1;
    }
  }, 5500);

  activeWorkerIndex.value = 0;
});

onUnmounted(() => clearTimers());
</script>
