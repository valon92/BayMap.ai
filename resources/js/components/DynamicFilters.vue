<template>
  <aside class="glass rounded-2xl p-4 lg:sticky lg:top-24 h-fit">
    <button
      type="button"
      class="lg:hidden flex w-full items-center gap-3 text-left"
      :aria-expanded="mobileOpen"
      aria-controls="ai-filters-panel"
      @click="mobileOpen = !mobileOpen"
    >
      <span
        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl transition-colors"
        :class="activeFilterCount ? 'bg-blue-600 text-white shadow-[0_4px_14px_-4px_rgba(37,99,235,0.45)]' : 'bg-blue-50 text-blue-600'"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
        </svg>
      </span>

      <span class="min-w-0 flex-1">
        <span class="flex items-center gap-2">
          <span class="font-semibold text-slate-900">{{ t('filters') }}</span>
          <span
            v-if="activeFilterCount"
            class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-blue-600 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white"
          >
            {{ activeFilterCount }}
          </span>
        </span>
        <span class="mt-0.5 block text-xs text-slate-500">
          {{ mobileOpen ? t('filters_hide') : (activeFilterCount ? t('filters_active_hint') : t('filters_show_hint')) }}
        </span>
      </span>

      <svg
        class="h-5 w-5 shrink-0 text-slate-400 transition-transform duration-300 ease-out"
        :class="{ 'rotate-180': mobileOpen }"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
        aria-hidden="true"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <div
      v-if="!mobileOpen && activeFilterChips.length"
      class="lg:hidden mt-3 flex flex-wrap gap-1.5"
    >
      <span
        v-for="chip in activeFilterChips"
        :key="chip.key"
        class="insight-chip insight-chip--accent text-[11px] py-1 px-2.5"
      >
        {{ chip.label }}
      </span>
    </div>

    <div class="hidden lg:flex items-center justify-between mb-4">
      <h3 class="font-semibold text-slate-900">{{ t('filters') }}</h3>
      <button
        v-if="hasActive"
        type="button"
        class="text-xs text-blue-600 hover:text-blue-700 font-medium"
        @click="clearAll"
      >
        {{ t('clear_filters') }}
      </button>
    </div>

    <div
      id="ai-filters-panel"
      class="filter-panel grid transition-[grid-template-rows] duration-300 ease-out lg:!grid-rows-[1fr]"
      :class="mobileOpen ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
    >
      <div class="min-h-0 overflow-hidden lg:overflow-visible">
        <div class="space-y-4" :class="mobileOpen ? 'pt-4 lg:pt-0' : 'lg:pt-0'">
          <div v-if="mobileOpen && hasActive" class="lg:hidden flex justify-end -mt-1">
            <button
              type="button"
              class="text-xs text-blue-600 hover:text-blue-700 font-medium"
              @click="clearAll"
            >
              {{ t('clear_filters') }}
            </button>
          </div>

          <div v-for="filter in filters" :key="filter.key" class="space-y-1.5">
            <label class="text-xs text-slate-500 font-medium">{{ filter.label }}</label>

            <select
              v-if="filter.type === 'select' || filter.type === 'sort'"
              :value="active[filter.key] ?? filter.value ?? (filter.type === 'sort' ? 'relevance' : '')"
              class="w-full rounded-lg bg-slate-50 border border-slate-200 text-sm text-slate-900 px-3 py-2 focus:ring-blue-500/30 focus:border-blue-400"
              @change="update(filter.key, $event.target.value || null)"
            >
              <option v-if="filter.type !== 'sort'" value="">{{ t('filter_any') }}</option>
              <option
                v-for="opt in filter.options"
                :key="optionValue(opt)"
                :value="optionValue(opt)"
              >
                {{ optionLabel(opt) }}
              </option>
            </select>

            <input
              v-else-if="filter.type === 'number'"
              type="number"
              :min="filter.min"
              :max="filter.max"
              :step="filter.step ?? 1"
              :value="active[filter.key] ?? filter.value ?? ''"
              :placeholder="filter.placeholder || '—'"
              class="w-full rounded-lg bg-slate-50 border border-slate-200 text-sm text-slate-900 px-3 py-2 focus:ring-blue-500/30 focus:border-blue-400"
              @input="update(filter.key, $event.target.value === '' ? null : $event.target.value)"
            />

            <template v-else-if="filter.type === 'range'">
              <input
                type="range"
                :min="filter.min"
                :max="filter.max"
                :value="active[filter.key] ?? filter.value ?? filter.min"
                class="w-full accent-blue-600"
                @input="update(filter.key, Number($event.target.value))"
              />
              <span v-if="active[filter.key] ?? filter.value" class="text-xs text-slate-500">
                {{ active[filter.key] ?? filter.value }}
              </span>
              <span v-else class="text-xs text-slate-600">
                {{ t('filter_any') }}
              </span>
            </template>
          </div>
        </div>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { computed, inject, ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  filters: { type: Array, default: () => [] },
  modelValue: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue', 'change']);
const { t } = inject('i18n');

const mobileOpen = ref(false);
const desktopQuery = typeof window !== 'undefined'
  ? window.matchMedia('(min-width: 1024px)')
  : null;

const active = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
});

const hasActive = computed(() => activeFilterChips.value.length > 0);

const activeFilterChips = computed(() => {
  const chips = [];

  for (const filter of props.filters) {
    const val = active.value[filter.key];
    if (val == null || val === '') continue;
    if (filter.type === 'sort' && (String(val) === 'relevance' || String(val) === 'popularity')) continue;

    chips.push({
      key: filter.key,
      label: `${filter.label}: ${displayValue(filter, val)}`,
    });
  }

  return chips;
});

const activeFilterCount = computed(() => activeFilterChips.value.length);

function displayValue(filter, val) {
  if (filter.type === 'select' || filter.type === 'sort') {
    const opt = (filter.options || []).find((o) => optionValue(o) === val);
    return opt ? optionLabel(opt) : String(val);
  }
  return String(val);
}

function update(key, value) {
  const next = { ...active.value };
  if (value === null || value === '') {
    delete next[key];
  } else {
    next[key] = value;
  }
  emit('update:modelValue', next);
  emit('change', next);
}

function clearAll() {
  emit('update:modelValue', {});
  emit('change', {});
}

function optionValue(opt) {
  return typeof opt === 'object' && opt !== null ? opt.value : opt;
}

function optionLabel(opt) {
  return typeof opt === 'object' && opt !== null ? opt.label : opt;
}

function syncViewport() {
  if (desktopQuery?.matches) {
    mobileOpen.value = false;
  }
}

onMounted(() => {
  syncViewport();
  desktopQuery?.addEventListener('change', syncViewport);
});

onUnmounted(() => {
  desktopQuery?.removeEventListener('change', syncViewport);
});
</script>
