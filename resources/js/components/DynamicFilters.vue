<template>
  <aside class="glass rounded-2xl p-4 lg:sticky lg:top-24 h-fit">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-semibold text-white">{{ t('filters') }}</h3>
      <button
        v-if="hasActive"
        type="button"
        class="text-xs text-sky-400 hover:text-sky-300"
        @click="clearAll"
      >
        {{ t('clear_filters') }}
      </button>
    </div>

    <div class="space-y-4">
      <div v-for="filter in filters" :key="filter.key" class="space-y-1.5">
        <label class="text-xs text-slate-400 font-medium">{{ filter.label }}</label>

        <select
          v-if="filter.type === 'select' || filter.type === 'sort'"
          :value="active[filter.key] ?? filter.value ?? (filter.type === 'sort' ? 'relevance' : '')"
          class="w-full rounded-lg bg-white/5 border border-white/10 text-sm text-white px-3 py-2 focus:ring-sky-500/40"
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
          class="w-full rounded-lg bg-white/5 border border-white/10 text-sm text-white px-3 py-2 focus:ring-sky-500/40"
          @input="update(filter.key, $event.target.value === '' ? null : $event.target.value)"
        />

        <input
          v-else-if="filter.type === 'range'"
          type="range"
          :min="filter.min"
          :max="filter.max"
          :value="active[filter.key] ?? filter.value ?? filter.min"
          class="w-full accent-sky-500"
          @input="update(filter.key, Number($event.target.value))"
        />
        <span v-if="filter.type === 'range' && (active[filter.key] ?? filter.value)" class="text-xs text-slate-500">
          {{ active[filter.key] ?? filter.value }}
        </span>
        <span v-else-if="filter.type === 'range'" class="text-xs text-slate-600">
          {{ t('filter_any') }}
        </span>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { computed, inject } from 'vue';

const props = defineProps({
  filters: { type: Array, default: () => [] },
  modelValue: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue', 'change']);
const { t } = inject('i18n');

const active = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
});

const hasActive = computed(() => Object.keys(active.value).length > 0);

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
</script>
